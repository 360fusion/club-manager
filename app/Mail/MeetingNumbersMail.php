<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Meeting;
use App\Support\Currencies;

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
    ) {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->club;
    }

    protected function templateKey(): string
    {
        return $this->change ? 'meeting_numbers_changed' : 'meeting_numbers';
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
                'meeting_url' => route('admin.meetings.show', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]),
            ],
            'html' => [],
        ];
    }

    protected function fallbackSubject(): string
    {
        $v = $this->values()['text'];

        return ($this->change ? 'Numbers changed' : 'Dining numbers').": {$v['meeting_title']}, {$v['dining']} dining";
    }

    protected function fallbackBody(): string
    {
        $v = $this->values()['text'];
        $n = $this->numbers;
        $list = fn (array $items) => $items ? '<ul>'.implode('', array_map(fn ($item) => '<li>'.e($item).'</li>', $items)).'</ul>' : '<p>None.</p>';

        return ($this->change ? '<p><strong>Changed:</strong> '.e($this->change).'</p>' : '<p>Replies for dining have closed. These are the numbers so far.</p>')
            .'<p><strong>'.e($v['meeting_title']).'</strong>, '.e($v['meeting_date']).'</p>'
            .'<p><strong>Dining: '.$n['dining'].'</strong> ('.$n['dining_members'].' members and visitors, '.$n['dining_guests'].' guests)<br>'
            .'Attending, not dining: '.$n['meeting_only'].'<br>'
            .'Apologies: '.count($n['apologies']).'<br>'
            .'No reply: '.count($n['no_reply']).'<br>'
            .'Dining unpaid: '.$n['unpaid_heads'].' ('.e(Currencies::format($n['outstanding'], $this->club)).')</p>'
            .'<h3>Apologies</h3>'.$list(array_map(fn ($a) => $a['name'].($a['reason'] ? ': '.$a['reason'] : ''), $n['apologies']))
            .'<h3>Dietary needs</h3>'.$list(array_map(fn ($d) => $d['person'].': '.$d['requirement'], $n['dietary']))
            .'<h3>No reply yet</h3>'.$list($n['no_reply'])
            .'<p><a href="'.e($v['meeting_url']).'">Open the meeting</a></p>';
    }
}
