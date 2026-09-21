<?php

namespace App\Console\Commands;

use App\Mail\EventPaymentReminderMail;
use App\Models\EventRegistration;
use App\Notifications\ClubNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventPaymentRemindersCommand extends Command
{
    protected $signature = 'app:send-event-payment-reminders {--days=3 : Remind this many days before the due date}';

    protected $description = 'Remind people with an unpaid event booking that payment is due soon or overdue';

    public function handle(): int
    {
        $sent = 0;

        EventRegistration::with(['event.club', 'paymentMethod', 'user'])
            ->where('status', 'attending')
            ->whereIn('payment_status', ['unpaid', 'part_paid'])
            ->where('total', '>', 0)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDays((int) $this->option('days')))
            ->where(fn ($q) => $q->whereNull('reminder_sent_at')->orWhere('reminder_sent_at', '<=', now()->subDays(7)))
            ->whereHas('event', fn ($q) => $q->where('starts_at', '>', now())->whereNotIn('status', ['cancelled', 'completed', 'draft']))
            ->orderBy('id')
            ->each(function (EventRegistration $registration) use (&$sent) {
                if ($registration->balanceDue() <= 0) {
                    return;
                }

                if ($registration->user) {
                    $registration->user->notify(ClubNotification::payment($registration, $registration->event->club));
                }

                if ($registration->contact_email) {
                    Mail::to($registration->contact_email)->send(new EventPaymentReminderMail($registration));
                }

                $registration->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            });

        $this->info("Sent {$sent} payment ".($sent === 1 ? 'reminder' : 'reminders').'.');

        return self::SUCCESS;
    }
}
