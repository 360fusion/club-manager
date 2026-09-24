<?php

namespace App\Support;

/**
 * Reads the way a province site describes when a lodge meets ("Last Thursday Jan to May, Sep to
 * Dec", "1st Wednesday each month (except August)") into a weekday, an occurrence and the months.
 *
 * It only reads the regular meeting. Installations, lodges of instruction and rehearsals are left
 * out, and anything it cannot read with confidence gives null so the original wording is shown
 * instead of a guessed date.
 */
class MeetingScheduleParser
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const MONTH_PATTERN = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';

    private const OCCURRENCES = [
        '1st' => '1st', 'first' => '1st', '2nd' => '2nd', 'second' => '2nd', '3rd' => '3rd',
        'third' => '3rd', '4th' => '4th', 'fourth' => '4th', 'forth' => '4th', 'last' => 'last',
    ];

    private const DAYS = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];

    /**
     * @return array{occurrence: string, day_of_week: string, months: list<int>, start_time: ?string}|null
     */
    public function parse(string $text): ?array
    {
        $original = trim($text);

        if ($original === '') {
            return null;
        }

        $time = $this->time($original);
        $clean = $this->clean($original);

        $pattern = '/\b(1st|first|2nd|second|3rd|third|4th|fourth|forth|last)\.?\s+(?:of\s+the\s+|of\s+)?(mon|tue|wed|thu|fri|sat|sun)[a-z]*/';

        if (str_contains($clean, 'penultimate') || ! preg_match($pattern, $clean, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // Several different patterns ("4th Wednesday ... 3rd Wednesday in December") cannot be shown as one.
        preg_match_all($pattern, $clean, $all, PREG_SET_ORDER);

        if (count(array_unique(array_map(fn (array $m) => self::OCCURRENCES[$m[1]].$m[2], $all))) > 1) {
            return null;
        }

        $occurrence = self::OCCURRENCES[$match[1][0]];
        $day = self::DAYS[$match[2][0]];
        $rest = substr($clean, $match[0][1] + strlen($match[0][0]));
        $months = $this->months($rest);

        // An installation meeting is a meeting too, even when the regular months leave its month out.
        if ($months !== [] && preg_match('/installation[^.;]*/', strtolower($original), $installation)) {
            preg_match_all('/\b('.self::MONTH_PATTERN.')\b/', $installation[0], $named);

            // A list of months after "Installation" is too ambiguous to trust.
            if (count($named[1]) > 1) {
                return null;
            }

            // An installation on its own weekday ("1st Monday in November") is not on the regular day.
            if ($named[1] !== [] && ! preg_match($pattern, $installation[0])) {
                $months[] = $this->monthNumber($named[1][0]);
                $months = array_values(array_unique($months));
                sort($months);
            }
        }

        if ($months === []) {
            return null;
        }

        return ['occurrence' => $occurrence, 'day_of_week' => $day, 'months' => $months, 'start_time' => $time];
    }

    /**
     * The month of the installation meeting, when the wording names exactly one: "Installation
     * December", "Installation is in October", or a month marked "(Inst)" or "(Installation)".
     */
    public function installationMonth(string $text): ?int
    {
        $text = strtolower($text);
        $month = self::MONTH_PATTERN;

        if (preg_match_all('/\b('.$month.')\b\.?\s*\(\s*inst(?:allation)?\.?\s*\)/', $text, $marked) && count($marked[1]) === 1) {
            return $this->monthNumber($marked[1][0]);
        }

        if (preg_match('/installation[^.;]*/', $text, $phrase)) {
            preg_match_all('/\b('.$month.')\b/', $phrase[0], $named);

            if (count($named[1]) === 1) {
                return $this->monthNumber($named[1][0]);
            }
        }

        return null;
    }

    /**
     * The text with everything that is not the regular meeting taken out.
     */
    private function clean(string $text): string
    {
        $text = strtolower($text);
        $text = str_replace(['–', '—', '&'], ['-', '-', ' and '], $text);
        $text = preg_replace('/\((?:inst(?:allation)?\.?|i)\)/', ' ', $text);
        $text = preg_replace('/\([^)]*(?:inst|start|meeting|time|\d\s*(?:pm|am|hrs))[^)]*\)/', ' ', $text);
        $text = str_replace(['(', ')'], ' ', $text);

        $sentences = preg_split('/(?<=[.;])\s+/', $text) ?: [$text];
        $sentences = array_filter($sentences, fn (string $sentence) => ! preg_match('/instruction|practice|rehears|emergency|refectory/', $sentence));
        $text = implode(' ', $sentences);

        $text = preg_replace('/[-,;.]?\s*installation\b[^.;]*/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    /**
     * @return list<int>
     */
    private function months(string $text): array
    {
        // "(July and August closed)": the months come before the word that excludes them.
        $before = [];
        $text = preg_replace_callback('/((?:'.self::MONTH_PATTERN.')(?:\s*(?:and|,|\/)\s*(?:'.self::MONTH_PATTERN.'))*)\s+(?:closed|dark|recess|holidays?)\b/', function (array $hit) use (&$before) {
            $before[] = $hit[1];

            return ' ';
        }, $text);

        $split = preg_split('/\b(?:except(?:ing)?|excluding|with the exception of|other than|apart from|not in|but|recess|closed|dark|no meetings?|(?:do|does) not meet|not meet|not held)\b/', $text, 2);
        $included = $split[0];
        $excluded = $split[1] ?? '';

        $months = [];
        $month = self::MONTH_PATTERN;

        $expand = function (int $from, int $to) use (&$months): void {
            for ($m = $from; ; $m = $m % 12 + 1) {
                $months[$m] = true;

                if ($m === $to) {
                    break;
                }
            }
        };

        $included = preg_replace_callback('/\bbetween\s+('.$month.')\b\.?\s+and\s+('.$month.')\b/', function (array $range) use ($expand) {
            $expand($this->monthNumber($range[1]), $this->monthNumber($range[2]));

            return ' ';
        }, $included);

        $included = preg_replace_callback('/\b('.$month.')\b\.?\s*(?:to|-|through|thru|till|until)\s*('.$month.')\b/', function (array $range) use ($expand) {
            $expand($this->monthNumber($range[1]), $this->monthNumber($range[2]));

            return ' ';
        }, (string) $included);

        preg_match_all('/\b('.$month.')\b/', (string) $included, $singles);

        foreach ($singles[1] as $name) {
            $months[$this->monthNumber($name)] = true;
        }

        $everyMonth = preg_match('/\b(?:each|every)\s+month|\bmonthly\b|\b(?:in|of)\s+(?:the\s+)?(?:each\s+)?month\b/', $split[0]);

        if ($months === [] && ($everyMonth || $excluded !== '' || $before !== [])) {
            $months = array_fill_keys(range(1, 12), true);
        }

        preg_match_all('/\b('.$month.')\b/', $excluded.' '.implode(' ', $before), $skipped);

        foreach ($skipped[1] as $name) {
            unset($months[$this->monthNumber($name)]);
        }

        $months = array_keys($months);
        sort($months);

        return $months;
    }

    private function monthNumber(string $name): int
    {
        return self::MONTHS[substr(strtolower($name), 0, 3)];
    }

    private function time(string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[:.](\d{2})\s*(pm|am|hrs|hours)?/i', $text, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $suffix = strtolower($m[3] ?? '');
        } elseif (preg_match('/\b(\d{1,2})\s*(pm|am)\b/i', $text, $m)) {
            $hour = (int) $m[1];
            $minute = 0;
            $suffix = strtolower($m[2]);
        } else {
            return null;
        }

        if ($suffix === 'pm' && $hour < 12) {
            $hour += 12;
        }

        if ($suffix === 'am' && $hour === 12) {
            $hour = 0;
        }

        return $hour <= 23 && $minute <= 59 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }
}
