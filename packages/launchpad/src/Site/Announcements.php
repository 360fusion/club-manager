<?php

namespace Launchpad\Site;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\Announcement;
use Launchpad\Models\Membership;
use Launchpad\Workspaces\Role;

/**
 * Which announcements a person sees: live ones for their audience (everyone; teams on the plans named; owners and admins), newest first, three at
 * most, less the ones they closed. The audience is judged on the team they are working in.
 */
final class Announcements
{
    private const SHOWN = 3;

    /** @return Collection<int, Announcement> */
    public function forUser(object $user): Collection
    {
        $dismissed = DB::table('announcement_dismissals')->where('user_id', $user->getKey())->pluck('announcement_id')->all();

        return Announcement::live()->whereNotIn('id', $dismissed)->orderByRaw('coalesce(starts_at, created_at) desc')->orderByDesc('id')->get()
            ->filter(fn (Announcement $a) => $this->isFor($a, $user))->take(self::SHOWN)->values();
    }

    public function isFor(Announcement $announcement, object $user): bool
    {
        if ($announcement->audience === 'everyone') {
            return true;
        }

        $workspace = method_exists($user, 'currentWorkspace') ? $user->currentWorkspace() : null;

        if ($workspace === null) {
            return false;
        }

        return match ($announcement->audience) {
            'plans' => in_array(app(Entitlements::class)->plan($workspace)->key, (array) $announcement->plans, true),
            'admins' => in_array(Membership::where(['workspace_id' => $workspace->id, 'user_id' => $user->getKey()])->first()?->role, [Role::Owner, Role::Admin], true),
            default => false,
        };
    }

    public function dismiss(Announcement $announcement, object $user): void
    {
        DB::table('announcement_dismissals')->insertOrIgnore(['announcement_id' => $announcement->id, 'user_id' => $user->getKey(), 'created_at' => now()]);
    }
}
