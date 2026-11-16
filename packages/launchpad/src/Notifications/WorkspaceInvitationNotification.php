<?php

namespace Launchpad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Launchpad\Mail\Renderer;
use Launchpad\Models\Invitation;
use Launchpad\Workspaces\Invitations;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation, private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;

        return Renderer::message('workspace.invitation', [
            'inviter' => $this->invitation->inviter?->name ?? 'Someone',
            'workspace' => $workspace->name,
            'role' => $this->invitation->role->label(),
            'days' => Invitations::DAYS,
            'url' => route('invitations.show', $this->token),
        ]);
    }
}
