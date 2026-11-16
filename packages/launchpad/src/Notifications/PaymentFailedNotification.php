<?php

namespace Launchpad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Launchpad\Mail\Renderer;
use Launchpad\Models\Workspace;

/** Stripe also emails the customer about a failed payment; this one is from the app, and says what it does to the plan. */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Workspace $workspace) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return Renderer::message('billing.payment_failed', ['workspace' => $this->workspace->name, 'url' => Route::has('workspace.billing') ? route('workspace.billing', $this->workspace->slug) : url('/')]);
    }
}
