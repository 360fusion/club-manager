<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The clubs a member's page is allowed to look at: all of their active
 * memberships, or just one of them. Every member-area query gets its clubs from
 * here, so one club's data cannot leak onto another club's page.
 */
final class MemberScope
{
    /**
     * @param  Collection<int, Club>  $memberClubs  every club the user is an active member of
     */
    private function __construct(
        public readonly User $user,
        public readonly Collection $memberClubs,
        public readonly ?Club $club,
    ) {}

    /**
     * Scope for a URL such as /members/{club}; a 404 when the user is not an active member.
     */
    public static function for(User $user, ?string $slug = null): self
    {
        $memberClubs = $user->clubs()->with('clubType')->wherePivot('status', 'active')->get();

        if ($slug === null) {
            return new self($user, $memberClubs, null);
        }

        $club = $memberClubs->firstWhere('slug', $slug)
            ?? ($user->is_super_admin ? Club::where('slug', $slug)->first() : null);

        abort_if($club === null, 404);

        return new self($user, $memberClubs, $club);
    }

    /**
     * Scope for a member page. A club in the URL (/members/{club}/...) is strict and
     * a 404 for anyone who is not an active member; the optional ?club= filter on the
     * all-clubs pages is lenient, and an unknown club is ignored.
     */
    public static function fromRequest(Request $request, ?string $slug = null): self
    {
        /** @var User $user */
        $user = $request->user();

        if ($slug !== null) {
            return self::for($user, $slug);
        }

        $filter = $request->query('club');
        $scope = self::for($user);

        if (! is_string($filter) || ! $scope->memberClubs->contains('slug', $filter)) {
            return $scope;
        }

        return self::for($user, $filter);
    }

    public function isSingle(): bool
    {
        return $this->club !== null;
    }

    /**
     * @return Collection<int, Club>
     */
    public function clubs(): Collection
    {
        return $this->club ? collect([$this->club]) : $this->memberClubs;
    }

    /**
     * @return Collection<int, int>
     */
    public function clubIds(): Collection
    {
        return $this->clubs()->pluck('id');
    }

    public function clubFor(int $clubId): ?Club
    {
        return $this->clubs()->firstWhere('id', $clubId);
    }

    public function roleIn(int $clubId): string
    {
        return $this->memberClubs->firstWhere('id', $clubId)?->pivot?->role ?? 'member';
    }

    public function isStaffIn(int $clubId): bool
    {
        return $this->roleIn($clubId) !== 'member';
    }
}
