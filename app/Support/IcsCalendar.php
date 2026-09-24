<?php

namespace App\Support;

use DateTimeInterface;

/**
 * A minimal RFC 5545 calendar writer for the personal calendar feed.
 */
class IcsCalendar
{
    /**
     * @var list<string>
     */
    private array $events = [];

    public function __construct(private readonly string $name, private readonly string $host) {}

    public function add(string $uid, string $summary, DateTimeInterface $start, DateTimeInterface $end, ?string $location = null, ?string $description = null, ?string $url = null): void
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$uid.'@'.$this->host,
            'DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'DTSTART:'.gmdate('Ymd\THis\Z', $start->getTimestamp()),
            'DTEND:'.gmdate('Ymd\THis\Z', $end->getTimestamp()),
            'SUMMARY:'.$this->escape($summary),
        ];

        if ($location) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        if ($description) {
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
        }

        if ($url) {
            $lines[] = 'URL:'.$url;
        }

        $lines[] = 'END:VEVENT';

        $this->events[] = implode("\r\n", array_map($this->fold(...), $lines));
    }

    /**
     * An event with a date and no time, for meetings whose start time is not known.
     */
    public function addAllDay(string $uid, string $summary, DateTimeInterface $date, ?string $location = null, ?string $description = null, ?string $url = null): void
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$uid.'@'.$this->host,
            'DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$date->format('Ymd'),
            'DTEND;VALUE=DATE:'.\DateTimeImmutable::createFromInterface($date)->modify('+1 day')->format('Ymd'),
            'SUMMARY:'.$this->escape($summary),
        ];

        if ($location) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        if ($description) {
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
        }

        if ($url) {
            $lines[] = 'URL:'.$url;
        }

        $lines[] = 'END:VEVENT';

        $this->events[] = implode("\r\n", array_map($this->fold(...), $lines));
    }

    public function render(): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ClubManager//Member calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            $this->fold('X-WR-CALNAME:'.$this->escape($this->name)),
            ...$this->events,
            'END:VCALENDAR',
        ])."\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /**
     * Lines longer than 75 octets are continued on the next line, per the RFC.
     */
    private function fold(string $line): string
    {
        $out = '';
        $limit = 75;

        while (strlen($line) > $limit) {
            $cut = $limit;

            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
            // Continuation lines begin with a space, which counts towards the limit.
            $limit = 74;
        }

        return $out.$line;
    }
}
