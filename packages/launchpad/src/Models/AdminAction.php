<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Launchpad\Launchpad;
use LogicException;

/** One thing a member of staff did. A row is never changed or deleted through the model. */
class AdminAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** The words for the log's action codes; an app adds its own with AdminLog::describe() */
    public const ACTIONS = [
        'user.created' => 'Account made',
        'user.updated' => 'Account edited',
        'user.email_verified' => 'Email marked as verified',
        'user.staff_changed' => 'Staff role changed',
        'user.impersonate_started' => 'Signed in as a customer',
        'user.impersonate_stopped' => 'Stopped signing in as a customer',
        'account.closed' => 'Account closed (kept until removed)',
        'account.restored' => 'Account restored',
        'account.purged' => 'Account removed for good',
        'workspace.updated' => 'Workspace edited',
        'plan_limit.changed' => 'Plan limit changed',
        'plan_limit.reset' => 'Plan limit back to the default',
        'feature.override' => 'Feature switched for a workspace',
        'announcement.created' => 'Announcement posted',
        'announcement.updated' => 'Announcement edited',
        'announcement.deleted' => 'Announcement removed',
        'blog_post.created' => 'Blog post written',
        'blog_post.updated' => 'Blog post edited',
        'blog_post.deleted' => 'Blog post removed',
        'waitlist.invited' => 'Invited from the waitlist',
        'roadmap.created' => 'Roadmap item added',
        'roadmap.updated' => 'Roadmap item changed',
        'roadmap.merged' => 'Roadmap items merged',
        'roadmap.deleted' => 'Roadmap item removed',
        'report.actioned' => 'Report acted on',
        'report.dismissed' => 'Report dismissed',
        'user.passkeys_removed' => 'Passkeys removed',
        'user.suspended' => 'Account suspended',
        'user.unsuspended' => 'Account suspension lifted',
        'workspace.suspended' => 'Team suspended',
        'workspace.unsuspended' => 'Team suspension lifted',
        'sso.unenforced' => 'Single sign-on no longer required',
        'sso.removed' => 'Single sign-on removed',
        'promo_code.created' => 'Promo code made',
        'promo_code.deactivated' => 'Promo code turned off',
        'plan.held' => 'Plan held back as coming soon',
        'plan.released' => 'Plan put on sale',
        'setting.changed' => 'Setting changed',
        'contact_message.updated' => 'Contact message handled',
        'email_template.updated' => 'Email template edited',
        'email_template.reset' => 'Email template back to the default',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('An admin action is never changed.'));
        static::deleting(fn () => throw new LogicException('An admin action is never deleted.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return AdminLogLabels::for($this->action);
    }
}
