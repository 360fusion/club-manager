<?php

namespace Launchpad\Workspaces;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Launchpad\Accounts\AccountPurging;
use Launchpad\Admin\Impersonation;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\ApiToken;
use Launchpad\Models\Invitation;
use Launchpad\Models\Membership;
use Launchpad\Models\WebhookEndpoint;
use Launchpad\Models\Workspace;
use Launchpad\Models\WorkspaceEvent;
use Launchpad\Models\WorkspaceFile;

/**
 * A team's activity log, for its admins (the Team activity page). Changes are noticed where they are made, on the models, so no controller has to
 * remember to write one; who did it is the person signed in for the request (or the token's maker), else the system. An app adds its own lines:
 * `Activity::record($workspace, 'project.created', 'created the project Apollo')`: a short sentence that goes after the person's name.
 */
final class Activity
{
    private static ?bool $ready = null;

    private static bool $silent = false;

    /** @param  string|null  $by  who, when it is not the person signed in (a name, e.g. "Billing") */
    public static function record(Workspace|int $workspace, string $action, string $summary, ?string $by = null): void
    {
        // not before the migration that makes the table has run
        self::$ready ??= Schema::hasTable('workspace_events');

        if (! self::$ready || self::$silent) {
            return;
        }

        $actor = $by === null ? self::actor() : null;
        $name = $by ?? ($actor ? $actor->name.(Impersonation::active() ? ' (via support)' : '') : 'The system');

        WorkspaceEvent::create([
            'workspace_id' => $workspace instanceof Workspace ? $workspace->getKey() : $workspace,
            'actor_id' => $actor?->getKey(), 'actor_name' => $name, 'action' => $action, 'summary' => Str::limit($summary, 500, ''),
            // the app's clock, not the database server's (useCurrent), which may run in another timezone or drift
            'created_at' => now(),
        ]);
    }

    /** Runs $do with nothing recorded: for a change that is written down once, as a whole, by whoever made it */
    public static function silently(Closure $do): mixed
    {
        $was = self::$silent;
        self::$silent = true;

        try {
            return $do();
        } finally {
            self::$silent = $was;
        }
    }

    /** The person signed in on the site, or the maker of the API token for this request */
    private static function actor(): ?object
    {
        return request()->user() ?? Auth::user();
    }

    public static function register(): void
    {
        // a person's name is taken off the lines when their account is removed for good
        Event::listen(AccountPurging::class, fn (AccountPurging $event) => WorkspaceEvent::withoutGlobalScope(WorkspaceScope::class)
            ->where('actor_id', $event->user->getKey())->update(['actor_name' => 'A former member']));

        Membership::created(function (Membership $m) {
            if ($m->role === Role::Owner && Membership::where('workspace_id', $m->workspace_id)->count() === 1) {
                return self::record($m->workspace_id, 'team.created', 'created the team');
            }

            self::record($m->workspace_id, 'member.added', self::actor()?->getKey() === $m->user_id
                ? 'joined the team'
                : "added {$m->user?->name} to the team as ".ucfirst($m->role->value));
        });

        Membership::updated(function (Membership $m) {
            $name = $m->user?->name;

            if ($m->wasChanged('role')) {
                $old = $m->getOriginal('role');
                $old = $old instanceof Role ? $old : Role::tryFrom((string) $old);
                self::record($m->workspace_id, 'member.role_changed', "changed {$name}'s role from ".ucfirst((string) $old?->value).' to '.ucfirst($m->role->value));
            }

            $m->wasChanged('permissions') && self::record($m->workspace_id, 'member.permissions_changed', "changed what {$name} may do");
        });

        Membership::deleted(fn (Membership $m) => self::record($m->workspace_id, 'member.removed', self::actor()?->getKey() === $m->user_id
            ? 'left the team'
            : "removed {$m->user?->name} from the team"));

        Invitation::created(fn (Invitation $i) => self::record($i->workspace_id, 'invitation.sent', "invited {$i->email} as ".ucfirst($i->role->value)));
        Invitation::updated(fn (Invitation $i) => $i->wasChanged('revoked_at') && $i->revoked_at !== null
            && self::record($i->workspace_id, 'invitation.cancelled', "cancelled the invitation for {$i->email}"));

        // a token is made for its workspace a moment after it is stored (ApiToken::issue)
        ApiToken::updated(fn (ApiToken $t) => $t->wasChanged('workspace_id') && $t->workspace_id !== null
            && self::record($t->workspace_id, 'api_token.created', "created the API token “{$t->name}”"));
        ApiToken::deleted(fn (ApiToken $t) => $t->workspace_id !== null && self::record($t->workspace_id, 'api_token.revoked', "revoked the API token “{$t->name}”"));

        $host = fn (WebhookEndpoint $e) => parse_url($e->url, PHP_URL_HOST) ?: $e->url;
        WebhookEndpoint::created(fn (WebhookEndpoint $e) => self::record($e->workspace_id, 'webhook.created', "added the webhook endpoint {$host($e)}"));
        WebhookEndpoint::updated(fn (WebhookEndpoint $e) => $e->wasChanged(['url', 'events', 'active']) && self::record($e->workspace_id, 'webhook.updated', "changed the webhook endpoint {$host($e)}"));
        WebhookEndpoint::deleted(fn (WebhookEndpoint $e) => self::record($e->workspace_id, 'webhook.deleted', "removed the webhook endpoint {$host($e)}"));

        WorkspaceFile::created(fn (WorkspaceFile $f) => self::record($f->workspace_id, 'file.uploaded', "uploaded {$f->name}"));
        WorkspaceFile::deleted(fn (WorkspaceFile $f) => self::record($f->workspace_id, 'file.deleted', "deleted {$f->name}"));

        Workspace::updated(function (Workspace $w) {
            $w->wasChanged('name') && self::record($w, 'team.renamed', "renamed the team from {$w->getOriginal('name')} to {$w->name}");
            $w->wasChanged('owner_id') && self::record($w, 'team.transferred', "made {$w->owner?->name} the owner of the team");
            $w->wasChanged('timezone') && self::record($w, 'team.timezone_changed', "set the team's time zone to {$w->timezone}");
            $w->wasChanged('role_permissions') && self::record($w, 'team.roles_changed', 'changed what each role can do');
        });

        // the plan follows the subscription, which Stripe's webhook writes
        $plan = function (Subscription $s) {
            if (! ($workspace = Workspace::find($s->workspace_id))) {
                return;
            }

            self::record($workspace, 'plan.changed', 'changed the plan to '.app(Entitlements::class)->plan($workspace)->name, 'Billing');
        };
        Subscription::created($plan);
        Subscription::updated(fn (Subscription $s) => $s->wasChanged(['stripe_price', 'stripe_status']) && $plan($s));
        Subscription::deleted($plan);
    }
}
