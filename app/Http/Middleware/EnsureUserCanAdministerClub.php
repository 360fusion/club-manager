<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Support\ClubPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorises club-scoped routes.
 *
 * Applies to any route carrying a {clubSlug} parameter. Without this, the club
 * is resolved purely from the URL, so any authenticated user could administer
 * any club by changing the slug.
 */
class EnsureUserCanAdministerClub
{
    /**
     * Pivot roles that may reach a club's admin area. Every role except the
     * plain 'member' is considered staff.
     */
    private const MEMBER_ONLY_ROLE = 'member';

    public function handle(Request $request, Closure $next): Response
    {
        // Only club admin routes are gated. Public and member-facing club routes
        // (the public site, the directory subscribe form, the member portal)
        // are reachable by non-members by design.
        if (! $this->isClubAdminRoute($request)) {
            return $next($request);
        }

        // Admin routes name the club either {clubSlug} or {slug}; both must be gated.
        $clubSlug = $request->route('clubSlug') ?? $request->route('slug');

        if (! is_string($clubSlug) || $clubSlug === '') {
            return $next($request);
        }

        $user = $request->user();

        // Unauthenticated requests are the auth middleware's business; letting
        // them through here keeps the login redirect instead of a bare 403.
        if (! $user) {
            return $next($request);
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        $club = Club::where('slug', $clubSlug)->first();

        if (! $club) {
            abort(404);
        }

        $membership = $user->clubs()->where('clubs.id', $club->id)->first();

        abort_if($membership === null, 403, 'You are not a member of this club.');

        abort_if($membership->pivot->status !== 'active', 403, 'Your membership of this club is not active.');

        $role = $membership->pivot->role;

        abort_if(
            $role === self::MEMBER_ONLY_ROLE,
            403,
            'You do not have administrative access to this club.',
        );

        // Mapped areas are further restricted by the club's permission matrix.
        // Unmapped areas fall back to "any staff role", which is what the
        // membership check above already established.
        $capability = ClubPermissions::capabilityForPath($request->path());

        if ($capability !== null) {
            abort_unless(
                ClubPermissions::allows($club, $role, $capability),
                403,
                'Your role does not have access to this area of the club.',
            );
        }

        return $next($request);
    }

    private function isClubAdminRoute(Request $request): bool
    {
        return (bool) preg_match('#^\{(?:clubSlug|slug)\}/admin(?:/|$)#', $request->route()?->uri() ?? '');
    }
}
