<?php

namespace App\Http\Middleware;

use App\Models\Club;
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

        $clubSlug = $request->route('clubSlug');

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

        abort_if(
            $membership->pivot->role === self::MEMBER_ONLY_ROLE,
            403,
            'You do not have administrative access to this club.',
        );

        return $next($request);
    }

    private function isClubAdminRoute(Request $request): bool
    {
        return $request->is('clubs/*/admin', 'clubs/*/admin/*');
    }
}
