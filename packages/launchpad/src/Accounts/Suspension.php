<?php

namespace Launchpad\Accounts;

use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\AdminLog;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\Activity;

/**
 * Staff block a person or a team for a while (abuse, a dispute, an unpaid bill) without closing anything; lifting it puts everything back.
 * A suspended person is signed out on their next request and cannot sign in or use an API key. A suspended team's pages show a notice to
 * its members, except the billing page, so an owner can still pay; its API keys are refused.
 */
final class Suspension
{
    public function suspend(Model $user, string $reason): void
    {
        $user->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();
        AdminLog::record('user.suspended', $user, "Suspended {$user->email}: {$reason}");
    }

    public function lift(Model $user): void
    {
        $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        AdminLog::record('user.unsuspended', $user, "Lifted the suspension of {$user->email}");
    }

    public function suspendTeam(Workspace $workspace, string $reason): void
    {
        $workspace->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();
        AdminLog::record('workspace.suspended', $workspace, "Suspended the team {$workspace->name}: {$reason}");
        Activity::record($workspace, 'team.suspended', 'suspended the team', 'Support');
    }

    public function liftTeam(Workspace $workspace): void
    {
        $workspace->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        AdminLog::record('workspace.unsuspended', $workspace, "Lifted the suspension of the team {$workspace->name}");
        Activity::record($workspace, 'team.unsuspended', 'lifted the team\'s suspension', 'Support');
    }
}
