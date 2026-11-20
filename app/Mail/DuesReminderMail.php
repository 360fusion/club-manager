<?php

namespace App\Mail;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;

/**
 * A friendly reminder that a member's dues are behind, overdue or nearly due (app:send-dues-reminders), with what is
 * paid and left and the lodge's bank details. `$dues` is DuesTracker::describe() for the subscription.
 */
class DuesReminderMail extends EventTemplatedMail
{
    /**
     * @param  array<string, mixed>  $dues
     * @param  array<string, mixed>|null  $bank  DuesTracker::bankDetails()
     */
    public function __construct(public Club $club, public Member $member, public array $dues, public ?array $bank)
    {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->club;
    }

    protected function templateKey(): string
    {
        return 'dues_reminder';
    }

    protected function values(): array
    {
        return [
            'text' => [
                'club_name' => $this->club->name,
                'member_name' => $this->member->formatted_rank_name,
                'year' => (string) $this->dues['year'],
                'amount_due' => $this->dues['amount_due'],
                'paid' => $this->dues['paid'],
                'left' => $this->dues['left'],
                'due_date' => $this->dues['due_date'],
                'progress' => $this->progress(),
                'reference' => (string) $this->dues['reference'],
                'dues_url' => route('members.dues'),
            ],
            'html' => ['bank_details' => $this->bankDetails()],
        ];
    }

    /** How their standing order is going; for someone paying in one go it would only repeat the sentence before. */
    private function progress(): string
    {
        return $this->dues['pace']['state'] === 'due' ? '' : $this->dues['pace']['message'];
    }

    private function bankDetails(): string
    {
        if (! $this->bank) {
            return '';
        }

        $lines = array_filter([
            $this->bank['account_name'] ? 'Account name: '.e($this->bank['account_name']) : null,
            $this->bank['sort_code'] ? e($this->bank['code_label'] ?: 'Sort code').': '.e($this->bank['sort_code']) : null,
            $this->bank['account_number'] ? 'Account number: '.e($this->bank['account_number']) : null,
            $this->bank['iban'] ? 'IBAN: '.e($this->bank['iban']) : null,
            $this->dues['reference'] ? '<strong>Reference: '.e($this->dues['reference']).'</strong>' : null,
        ]);

        return '<p style="background:#f1f5f9;padding:10px;border-radius:6px;"><strong>Pay by bank transfer, in one go or by a monthly standing order</strong><br>'
            .implode('<br>', $lines).'</p>';
    }

    protected function fallbackSubject(): string
    {
        return 'Your '.$this->dues['year'].' dues: '.$this->dues['left'].' still to pay';
    }

    protected function fallbackBody(): string
    {
        return '<p>Dear '.e($this->member->formatted_rank_name).',</p>'
            .'<p>You have paid '.e($this->dues['paid']).' of '.e($this->dues['amount_due']).', so '.e($this->dues['left']).' is still to pay, due by '.e($this->dues['due_date']).'.</p>'
            .'<p>'.e($this->progress()).'</p>'.$this->bankDetails()
            .'<p><a href="'.e(route('members.dues')).'">See your dues and payments</a></p>';
    }
}
