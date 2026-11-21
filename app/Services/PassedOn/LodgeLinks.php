<?php

namespace App\Services\PassedOn;

use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\ClubLodgeLink;
use App\Models\Lodge;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A lodge's two lists of other lodges. Sister lodges start as every lodge in the same Masonic hall (following the
 * directory, so a lodge added to the hall later appears), less any the Secretary unticked, plus any added by hand.
 * Reciprocal visit lodges are only ever added by hand.
 */
class LodgeLinks
{
    /**
     * @return Collection<int, Lodge>
     */
    public function lodges(Club $club, string $relation): Collection
    {
        $links = ClubLodgeLink::where('club_id', $club->id)->where('relation', $relation)->get();
        $own = $club->lodge;
        $hallId = $relation === ClubLodgeLink::SISTER ? $own?->masonic_hall_id : null;
        $added = $links->where('excluded', false)->pluck('lodge_id')->all();

        return Lodge::listed()
            ->where(fn ($query) => $query->whereIn('id', $added)->when($hallId, fn ($inHall) => $inHall->orWhere('masonic_hall_id', $hallId)))
            ->whereNotIn('id', $links->where('excluded', true)->pluck('lodge_id')->all())
            ->when($own, fn ($query) => $query->where('id', '!=', $own->id))
            ->with('clubType:id,code,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * The hall's other lodges, each marked `excluded` when the Secretary has unticked it.
     *
     * @return Collection<int, Lodge>
     */
    public function hallLodges(Club $club): Collection
    {
        $own = $club->lodge;
        if (! $own?->masonic_hall_id) {
            return collect();
        }

        $excluded = ClubLodgeLink::where('club_id', $club->id)->where('relation', ClubLodgeLink::SISTER)->where('excluded', true)->pluck('lodge_id')->all();

        return Lodge::listed()->where('masonic_hall_id', $own->masonic_hall_id)->where('id', '!=', $own->id)
            ->with('clubType:id,code,name')->orderBy('name')->get()
            ->each(fn (Lodge $lodge) => $lodge->setAttribute('excluded', in_array($lodge->id, $excluded, true)));
    }

    public function onList(Club $club, Lodge $lodge, string $relation): bool
    {
        return $this->lodges($club, $relation)->contains('id', $lodge->id);
    }

    public function add(Club $club, Lodge $lodge, string $relation): void
    {
        if ($this->inHall($club, $lodge, $relation)) {
            ClubLodgeLink::where(['club_id' => $club->id, 'lodge_id' => $lodge->id, 'relation' => $relation])->delete();

            return;
        }

        ClubLodgeLink::updateOrCreate(['club_id' => $club->id, 'lodge_id' => $lodge->id, 'relation' => $relation], ['excluded' => false]);
    }

    public function remove(Club $club, Lodge $lodge, string $relation): void
    {
        if ($this->inHall($club, $lodge, $relation)) {
            ClubLodgeLink::updateOrCreate(['club_id' => $club->id, 'lodge_id' => $lodge->id, 'relation' => $relation], ['excluded' => true]);

            return;
        }

        ClubLodgeLink::where(['club_id' => $club->id, 'lodge_id' => $lodge->id, 'relation' => $relation])->delete();
    }

    /**
     * Every club that has $lodge on one of its lists, with the list (a sister lodge wins over reciprocal).
     *
     * @return Collection<int, array{club: Club, relation: string}>
     */
    public function clubsListing(Lodge $lodge): Collection
    {
        $links = ClubLodgeLink::where('lodge_id', $lodge->id)->get();
        $excludedClubs = $links->where('relation', ClubLodgeLink::SISTER)->where('excluded', true)->pluck('club_id')->all();

        $sister = $links->where('relation', ClubLodgeLink::SISTER)->where('excluded', false)->pluck('club_id');
        if ($lodge->masonic_hall_id) {
            $sister = $sister->merge(
                Club::whereHas('lodge', fn ($own) => $own->where('masonic_hall_id', $lodge->masonic_hall_id)->where('id', '!=', $lodge->id))
                    ->whereNotIn('id', $excludedClubs)->pluck('id')
            );
        }
        $reciprocal = $links->where('relation', ClubLodgeLink::RECIPROCAL)->pluck('club_id');

        $relations = $reciprocal->mapWithKeys(fn (int $id) => [$id => ClubLodgeLink::RECIPROCAL])
            ->replace($sister->mapWithKeys(fn (int $id) => [$id => ClubLodgeLink::SISTER]))
            ->except([$lodge->club_id]);

        return Club::whereIn('id', $relations->keys())->get()
            ->map(fn (Club $club) => ['club' => $club, 'relation' => $relations->get($club->id, ClubLodgeLink::SISTER)])
            ->values();
    }

    private function inHall(Club $club, Lodge $lodge, string $relation): bool
    {
        $hallId = $club->lodge?->masonic_hall_id;

        return $relation === ClubLodgeLink::SISTER && $hallId !== null && $lodge->masonic_hall_id === $hallId;
    }

    /**
     * Lodges a member might follow: those sharing a hall with their own lodges and the ones each of their lodges lists
     * as sister or reciprocal visit lodges, less any they already follow or belong to. Shown on My lodges and the
     * directory.
     *
     * @return list<array{slug: string, name: string, order: ?string, meets_text: ?string, reason: string}>
     */
    public function suggestionsFor(User $user, int $limit = 12): array
    {
        $clubs = $user->clubs()->wherePivot('status', ClubMemberStatus::Active->value)->with('lodge')->orderBy('name')->get();
        $skip = $user->followedLodges()->pluck('lodges.id')->merge($clubs->pluck('lodge.id')->filter())->all();

        $suggested = collect();
        foreach ($clubs as $club) {
            $hallId = $club->lodge?->masonic_hall_id;
            foreach ([ClubLodgeLink::SISTER, ClubLodgeLink::RECIPROCAL] as $relation) {
                foreach ($this->lodges($club, $relation) as $lodge) {
                    $reason = match (true) {
                        $hallId !== null && $lodge->masonic_hall_id === $hallId => 'Meets at your hall',
                        $relation === ClubLodgeLink::SISTER => 'A sister lodge of '.$club->name,
                        default => $club->name.' visits it',
                    };
                    $suggested->put($lodge->id, $suggested->get($lodge->id) ?? ['lodge' => $lodge, 'reason' => $reason]);
                }
            }
        }

        return $suggested->except($skip)->take($limit)->map(fn (array $entry) => [
            'slug' => $entry['lodge']->slug,
            'name' => $entry['lodge']->displayName(),
            'order' => $entry['lodge']->clubType?->name,
            'meets_text' => $entry['lodge']->meets_text,
            'reason' => $entry['reason'],
        ])->values()->all();
    }
}
