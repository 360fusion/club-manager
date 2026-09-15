<?php

namespace App\Domains\ClubAccounting\Notifications;

use App\Domains\ClubAccounting\Models\ClubCommitteeTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommitteeTaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public ClubCommitteeTask $task)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meetingTitle = $this->task->meeting ? $this->task->meeting->title : 'Lodge Committee';
        $due = $this->task->due_date ? $this->task->due_date->format('d M Y') : 'As soon as possible';

        return (new MailMessage)
            ->subject("Lodge Committee Task Assigned: {$this->task->title}")
            ->greeting("Dear Brother {$notifiable->name},")
            ->line("You have been assigned a task arising from the recent meeting of the {$meetingTitle}:")
            ->line("**Task:** {$this->task->title}")
            ->line("**Due Date:** {$due}")
            ->when($this->task->description, fn ($mail) => $mail->line("**Details:** {$this->task->description}"))
            ->line('Thank you for your service to the Lodge.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'due_date' => $this->task->due_date?->format('Y-m-d'),
            'committee_meeting_id' => $this->task->committee_meeting_id,
        ];
    }
}
