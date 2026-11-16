<?php

namespace Launchpad\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Launchpad\Mail\Renderer;
use Launchpad\Models\Workspace;

/** Three days before a free trial ends: what happens next, and where to cancel */
class TrialEndingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Workspace $workspace, public CarbonInterface $endsAt) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Renderer::message('billing.trial_ending', ['workspace' => $this->workspace->name, 'date' => $this->endsAt->toFormattedDateString(), 'url' => Route::has('workspace.billing') ? route('workspace.billing', $this->workspace->slug) : url('/')]);
    }

    /** @return array{title: string, message: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your free trial ends soon'),
            'message' => __('The trial of :team ends on :date.', ['team' => $this->workspace->name, 'date' => $this->endsAt->toFormattedDateString()]),
            'url' => Route::has('workspace.billing') ? route('workspace.billing', $this->workspace->slug, false) : url('/'),
        ];
    }
}
