<?php

namespace App\Models\Concerns;

use App\Enums\Visibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds an audience setting to club content (posts, events).
 *
 * @property Visibility $visibility
 */
trait HasVisibility
{
    public function initializeHasVisibility(): void
    {
        $this->mergeCasts(['visibility' => Visibility::class]);
        $this->attributes['visibility'] ??= Visibility::Club->value;
    }

    /**
     * Limit the query to rows the given viewer is allowed to see.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer?->is_super_admin) {
            return $query;
        }

        $model = $query->getModel();
        $visibility = $model->qualifyColumn('visibility');
        $clubId = $model->qualifyColumn('club_id');
        $memberClubIds = $viewer?->activeClubIds() ?? collect();

        return $query->where(function (Builder $audience) use ($visibility, $clubId, $memberClubIds): void {
            $audience->where($visibility, Visibility::Public->value);

            if ($memberClubIds->isEmpty()) {
                return;
            }

            $audience->orWhere($visibility, Visibility::Network->value)
                ->orWhere(fn (Builder $own) => $own
                    ->where($visibility, Visibility::Club->value)
                    ->whereIn($clubId, $memberClubIds->all()));
        });
    }

    public function isVisibleTo(?User $viewer): bool
    {
        return $this->visibility->includes($viewer, (int) $this->club_id);
    }
}
