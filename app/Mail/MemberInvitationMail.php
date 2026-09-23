<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\User;

/**
 * Invites a member to set up an online account (or, with $reminder, nudges one who has not yet). The wording is the
 * `account_invitation` / `account_invitation_reminder` template, which a lodge can override for itself.
 */
class MemberInvitationMail extends EventTemplatedMail
{
    public function __construct(
        public Club $club,
        public User $user,
        public string $invitationToken,
        public string $acceptUrl,
        public ?User $inviter = null,
        public bool $reminder = false,
    ) {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->club;
    }

    protected function templateKey(): string
    {
        return $this->reminder ? 'account_invitation_reminder' : 'account_invitation';
    }

    protected function values(): array
    {
        return [
            'text' => [
                'member_name' => $this->user->name,
                'club_name' => $this->club->name,
                'invite_url' => $this->acceptUrl,
                'expiry_days' => $this->club->inviteExpirationDays(),
                'inviter_name' => $this->inviter?->name ?? $this->club->name,
            ],
            'html' => [],
        ];
    }

    protected function fallbackSubject(): string
    {
        return $this->reminder
            ? "Reminder: your {$this->club->name} invitation"
            : "You're invited to join {$this->club->name}";
    }

    protected function fallbackBody(): string
    {
        $values = $this->values()['text'];

        return '<p>Dear '.e($values['member_name']).',</p><p>'.e($values['club_name']).' has invited you to set up your online member account.</p><p><a href="'.e($values['invite_url']).'">Accept invitation and set up your account</a></p><p>This invitation link will expire in '.e((string) $values['expiry_days']).' days.</p>';
    }
}
