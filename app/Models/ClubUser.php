<?php

namespace App\Models;

use App\Enums\ClubRole;
use App\Support\ClubTeamEvents;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A row of club_user. Being the pivot model of Club::users() and User::clubs() means every attach, detach, sync and pivot update fires
 * model events, so a member joining, leaving, or changing role or status is recorded once, in the club's activity log (Launchpad's) and
 * sent to its webhooks, whichever screen or service made the change. Other columns (rank, contact details, committee role) record nothing.
 */
class ClubUser extends Pivot
{
    protected $table = 'club_user';

    public $incrementing = true;

    protected static function booted(): void
    {
        static::created(fn (self $row) => $row->record('member.added', 'added :name to the club as :role'));
        static::updated(function (self $row) {
            $row->wasChanged('role') && $row->record('member.role_changed', "changed :name's role to :role");
            $row->wasChanged('status') && $row->record('member.status_changed', "changed :name's status to :status");
        });
        static::deleted(fn (self $row) => $row->record('member.removed', 'removed :name from the club'));
    }

    private function record(string $event, string $sentence): void
    {
        $user = User::find($this->user_id);

        if (! $user) {
            return;
        }

        // a pivot row deleted by detach() carries only its keys: the role and status are read where they still are
        $role = $this->role ?? null;
        $status = $this->status ?? null;
        // as the screens name it: the coach role is shown as Secretary
        $roleLabel = $role === ClubRole::Coach->value ? 'Secretary' : ucfirst((string) $role);

        ClubTeamEvents::record((int) $this->club_id, $event, strtr($sentence, [':name' => $user->name, ':role' => $roleLabel, ':status' => (string) $status]),
            ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role, 'status' => $status]);
    }
}
