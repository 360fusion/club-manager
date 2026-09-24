<?php

namespace Tests\Unit;

use App\Support\MeetingScheduleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MeetingScheduleParserTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array{0: string, 1: string, 2: list<int>, 3: ?string}}>
     */
    public static function readable(): array
    {
        return [
            'every month except some' => ['We meet on the fourth Monday of each month with the exception of June, July, August and December.', ['4th', 'Monday', [1, 2, 3, 4, 5, 9, 10, 11], null]],
            'each month, one excluded, installation named' => ['1st Wednesday each month (except August) - Installation December.', ['1st', 'Wednesday', [1, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12], null]],
            'two ranges' => ['Last Thursday Jan to May, Sep to Dec.', ['last', 'Thursday', [1, 2, 3, 4, 5, 9, 10, 11, 12], null]],
            'a range across the new year' => ['The 4th Monday of the month September to June (installation April).', ['4th', 'Monday', [1, 2, 3, 4, 5, 6, 9, 10, 11, 12], null]],
            'between two months' => ['The Lodge meets ten times a year on the second Thursday of the month, between September and June, except for July and August. Lodge proceedings normally start at 7:00pm.', ['2nd', 'Thursday', [1, 2, 3, 4, 5, 6, 9, 10, 11, 12], '19:00']],
            'a list of months' => ['2nd Friday in March, May, October and November (Installation).', ['2nd', 'Friday', [3, 5, 10, 11], null]],
            'the word "and" is not a range' => ['1st Tuesday in October and December.', ['1st', 'Tuesday', [10, 12], null]],
            'closed months in brackets' => ['The first Wednesday of each month (July & August closed).', ['1st', 'Wednesday', [1, 2, 3, 4, 5, 6, 9, 10, 11, 12], null]],
            'a summer recess' => ['The first Friday of each month with a summer recess during July and August.', ['1st', 'Friday', [1, 2, 3, 4, 5, 6, 9, 10, 11, 12], null]],
            'installation adds its own month' => ['1st Wednesday of the month November-July. Installation October.', ['1st', 'Wednesday', [1, 2, 3, 4, 5, 6, 7, 10, 11, 12], null]],
            'abbreviations and 24 hour time' => ['Last Tue Sep, Nov, Jan, Mar at 18:30hrs', ['last', 'Tuesday', [1, 3, 9, 11], '18:30']],
            'from a province table' => ['1st Monday Jan, Mar, Apr, May, Sep, Oct, Nov, Dec', ['1st', 'Monday', [1, 3, 4, 5, 9, 10, 11, 12], null]],
            'a full stop after the number' => ['3rd. Thursday in month (except July & August).', ['3rd', 'Thursday', [1, 2, 3, 4, 5, 6, 9, 10, 11, 12], null]],
        ];
    }

    /**
     * @param  array{0: string, 1: string, 2: list<int>, 3: ?string}  $expected
     */
    #[DataProvider('readable')]
    public function test_reads_the_regular_meeting(string $text, array $expected): void
    {
        $this->assertSame(
            ['occurrence' => $expected[0], 'day_of_week' => $expected[1], 'months' => $expected[2], 'start_time' => $expected[3]],
            (new MeetingScheduleParser)->parse($text),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unreadable(): array
    {
        return [
            'nothing' => [''],
            'no months' => ['3rd Tuesday.'],
            'no weekday' => ['Meets monthly in the evening.'],
            'prose' => ['We have some 40 members and a wide range of ages.'],
            'two different patterns' => ['4th Wednesday September to June 3rd Wednesday December'],
            'penultimate' => ['We meet 4 times a year, on the penultimate Thursday in February, April and October.'],
            'a list of installation months' => ['2nd Monday January - Installation March May July September November'],
        ];
    }

    #[DataProvider('unreadable')]
    public function test_gives_nothing_rather_than_guessing(string $text): void
    {
        $this->assertNull((new MeetingScheduleParser)->parse($text));
    }

    public function test_an_installation_on_another_weekday_does_not_add_its_month(): void
    {
        $result = (new MeetingScheduleParser)->parse('4th Thursday in January, April and June. Installation on 1st Monday in November');

        $this->assertSame([1, 4, 6], $result['months']);
    }

    /**
     * @return array<string, array{0: string, 1: ?int}>
     */
    public static function installations(): array
    {
        return [
            'a sentence' => ['1st Wednesday each month (except August) - Installation December.', 12],
            'is in' => ['3rd Wednesday of the month March till December. Installation is in October.', 10],
            'a marked month' => ['2nd Friday in March, May, October and November (Installation).', 11],
            'marked with inst' => ['First Tuesday in January (Inst), March, April, May.', 1],
            'no installation' => ['Last Thursday Jan to May, Sep to Dec.', null],
            'several months are too unclear' => ['2nd Monday January - Installation March May July September November', null],
        ];
    }

    #[DataProvider('installations')]
    public function test_reads_the_installation_month(string $text, ?int $month): void
    {
        $this->assertSame($month, (new MeetingScheduleParser)->installationMonth($text));
    }

    public function test_reads_several_patterns_when_each_names_its_own_months(): void
    {
        $patterns = (new MeetingScheduleParser)->parseAll('Last Tuesday in February (Installation). Last Wednesday in May. Last Wednesday in November at 18:30');

        $this->assertSame([
            ['occurrence' => 'last', 'day_of_week' => 'Tuesday', 'months' => [2], 'start_time' => '18:30'],
            ['occurrence' => 'last', 'day_of_week' => 'Wednesday', 'months' => [5, 11], 'start_time' => '18:30'],
        ], $patterns);
    }

    public function test_run_on_wording_with_two_patterns_is_split(): void
    {
        $patterns = (new MeetingScheduleParser)->parseAll('4th Wednesday September to June 3rd Wednesday December');

        // The December meeting is on another Wednesday, but the first pattern already claims December.
        $this->assertSame([], $patterns);

        $patterns = (new MeetingScheduleParser)->parseAll('4th Tue Sep, Feb & Jun. 3rd Mon Nov & Apr');
        $this->assertSame([
            ['occurrence' => '4th', 'day_of_week' => 'Tuesday', 'months' => [2, 6, 9], 'start_time' => null],
            ['occurrence' => '3rd', 'day_of_week' => 'Monday', 'months' => [4, 11], 'start_time' => null],
        ], $patterns);
    }

    public function test_patterns_that_only_differ_by_month_are_merged(): void
    {
        $patterns = (new MeetingScheduleParser)->parseAll('4th Saturday in April, 3rd Friday in June, 3rd Friday in September, 3rd Saturday in October.');

        $this->assertSame(['4th|Saturday|4', '3rd|Friday|6,9', '3rd|Saturday|10'], array_map(fn ($p) => "{$p['occurrence']}|{$p['day_of_week']}|".implode(',', $p['months']), $patterns));
    }

    public function test_a_single_pattern_still_comes_back_as_one(): void
    {
        $this->assertCount(1, (new MeetingScheduleParser)->parseAll('Last Thursday Jan to May, Sep to Dec.'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function ambiguousAsSeveral(): array
    {
        return [
            'an exception inside' => ['1st Monday Jan to May except March. 2nd Friday September'],
            'no months given' => ['1st Monday. 2nd Friday.'],
            'overlapping months' => ['1st Monday January, March. 2nd Friday March, May'],
            'penultimate' => ['penultimate Thursday in February and the last Thursday in June, first Monday in May'],
            'nothing' => ['Meets when the moon is full'],
        ];
    }

    #[DataProvider('ambiguousAsSeveral')]
    public function test_several_patterns_are_not_guessed(string $text): void
    {
        $this->assertSame([], (new MeetingScheduleParser)->parseAll($text));
    }
}
