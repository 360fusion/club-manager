<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Meeting;
use App\Support\Currencies;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Str;

/**
 * The Secretary's numbers for a meeting once dining replies close, and again with what changed whenever someone changes
 * their reply after that.
 */
class MeetingNumbersMail extends EventTemplatedMail
{
    /**
     * @param  array{dining: int, dining_members: int, dining_guests: int, meeting_only: int, apologies: list<array{name: string, reason: ?string}>, no_reply: list<string>, dietary: list<array{person: string, requirement: string}>, unpaid_heads: int, outstanding: float}  $numbers
     */
    public function __construct(
        public Club $club,
        public Meeting $meeting,
        public array $numbers,
        public ?string $change = null,
        public bool $forCaterer = false,
    ) {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->club;
    }

    protected function templateKey(): string
    {
        return match (true) {
            $this->forCaterer => 'meeting_numbers_caterer',
            $this->change !== null => 'meeting_numbers_changed',
            default => 'meeting_numbers',
        };
    }

    protected function values(): array
    {
        return [
            'text' => [
                'club_name' => $this->club->name,
                'meeting_title' => $this->meeting->title,
                'meeting_date' => $this->meeting->meeting_date->format('l j F Y'),
                'dining' => (string) $this->numbers['dining'],
                'change' => (string) $this->change,
                'venue' => (string) $this->meeting->venue,
                'meeting_url' => route('admin.meetings.show', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]),
            ],
            'html' => [
                'numbers' => $this->numbersHtml(),
            ],
        ];
    }

    private function numbersHtml(): string
    {
        $n = $this->numbers;
        $list = fn (array $items) => $items ? '<ul>'.implode('', array_map(fn ($item) => '<li>'.e($item).'</li>', $items)).'</ul>' : '<p>None.</p>';

        // The caterer gets what the kitchen needs and nothing about who is away or who has paid.
        if ($this->forCaterer) {
            return '<p><strong>Dining: '.$n['dining'].'</strong> ('.$n['dining_members'].' members and visitors, '.$n['dining_guests'].' '.Str::plural('guest', $n['dining_guests']).')</p>'
                .($this->meeting->festive_board_menu ? '<h3>Menu</h3><p>'.nl2br(e($this->meeting->festive_board_menu)).'</p>' : '')
                .'<h3>Dietary needs</h3>'.$list(array_map(fn ($d) => $d['person'].': '.$d['requirement'], $n['dietary']));
        }

        return '<p><strong>Dining: '.$n['dining'].'</strong> ('.$n['dining_members'].' members and visitors, '.$n['dining_guests'].' '.Str::plural('guest', $n['dining_guests']).')<br>'
            .'Attending, not dining: '.$n['meeting_only'].'<br>'
            .'Apologies: '.count($n['apologies']).'<br>'
            .'No reply: '.count($n['no_reply']).'<br>'
            .'Dining unpaid: '.$n['unpaid_heads'].' ('.e(Currencies::format($n['outstanding'], $this->club)).')</p>'
            .'<h3>Apologies</h3>'.$list(array_map(fn ($a) => $a['name'].($a['reason'] ? ': '.$a['reason'] : ''), $n['apologies']))
            .'<h3>Dietary needs</h3>'.$list(array_map(fn ($d) => $d['person'].': '.$d['requirement'], $n['dietary']))
            .'<h3>No reply yet</h3>'.$list($n['no_reply']);
    }

    /**
     * Whatever the wording, the numbers themselves are always in the email.
     */
    public function content(): Content
    {
        $body = $this->filled()['body'];

        if (! str_contains($body, 'Dining: '.$this->numbers['dining'])) {
            $body .= $this->numbersHtml();
        }

        return new Content(html: 'emails.templated', with: ['bodyHtml' => $body]);
    }

    protected function fallbackSubject(): string
    {
        $v = $this->values()['text'];

        return ($this->change ? 'Numbers changed' : 'Dining numbers').": {$v['meeting_title']}, ".($this->forCaterer ? $v['meeting_date'].', ' : '')."{$v['dining']} dining";
    }

    protected function fallbackBody(): string
    {
        $v = $this->values()['text'];

        if ($this->forCaterer) {
            return ($this->change ? '<p><strong>'.e($this->change).'</strong></p>' : '<p>The numbers for the festive board.</p>')
                .'<p><strong>'.e($this->club->name).': '.e($v['meeting_title']).'</strong>, '.e($v['meeting_date']).($v['venue'] ? '<br>'.e($v['venue']) : '').'</p>'
                .$this->numbersHtml();
        }

        return ($this->change ? '<p><strong>Changed:</strong> '.e($this->change).'</p>' : '<p>Replies for dining have closed. These are the numbers so far.</p>')
            .'<p><strong>'.e($v['meeting_title']).'</strong>, '.e($v['meeting_date']).'</p>'
            .$this->numbersHtml()
            .'<p><a href="'.e($v['meeting_url']).'">Open the meeting</a></p>';
    }
}
