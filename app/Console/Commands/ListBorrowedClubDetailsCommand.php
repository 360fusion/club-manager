<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Meeting;
use Illuminate\Console\Command;

class ListBorrowedClubDetailsCommand extends Command
{
    /** What an older migration gave every club and meeting as a column default. */
    private const OLD_LODGE_NUMBER = '1418';

    private const OLD_MOTTO = 'Fraternus Amor Maneto';

    private const OLD_OFFICERS_HEADING = 'OFFICERS FOR 2025-2026';

    protected $signature = 'clubs:borrowed-details
        {--clear : Empty those values on the clubs and meetings listed (after asking), so each lodge can enter its own}
        {--except=* : Slug of a club that really has those values and must be left alone}
        {--force : With --clear, do not ask first (for a server where nobody can answer a prompt, such as Forge)}';

    protected $description = 'List the clubs and meetings that still hold the lodge number, motto and officers heading an old migration gave everyone. It only reads unless you pass --clear, which empties the old values after asking and skips any club named with --except.';

    public function handle(): int
    {
        $clubs = Club::query()
            ->where(fn ($query) => $query->where('lodge_number', self::OLD_LODGE_NUMBER)->orWhere('motto', self::OLD_MOTTO))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'lodge_number', 'motto']);

        $meetings = Meeting::query()
            ->where('officers_year_label', self::OLD_OFFICERS_HEADING)
            ->selectRaw('club_id, count(*) as total')
            ->groupBy('club_id')
            ->pluck('total', 'club_id');

        if ($clubs->isEmpty() && $meetings->isEmpty()) {
            $this->info('No club or meeting holds those values.');

            return self::SUCCESS;
        }

        $this->table(
            ['Club', 'Slug', 'Lodge number', 'Motto', 'Meetings headed "'.self::OLD_OFFICERS_HEADING.'"'],
            $clubs->map(fn (Club $club) => [$club->name, $club->slug, $club->lodge_number, $club->motto, $meetings->get($club->id, 0)])->all(),
        );

        $withoutClubRow = $meetings->keys()->diff($clubs->pluck('id'));
        if ($withoutClubRow->isNotEmpty()) {
            $this->line('Clubs with only the officers heading: '.Club::whereIn('id', $withoutClubRow)->orderBy('name')->pluck('name')->implode(', '));
        }

        if (! $this->option('clear')) {
            $this->comment('Lodge '.self::OLD_LODGE_NUMBER.' and that motto are real for one lodge, so nothing is changed unless you run this with --clear (and --except=<that lodge\'s slug>).');

            return self::SUCCESS;
        }

        $except = array_filter((array) $this->option('except'));

        if (! $this->option('force') && ! $this->confirm('Empty these values on every club and meeting listed'.($except ? ' except '.implode(', ', $except) : '').'?')) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $clubIds = Club::query()->whereNotIn('slug', $except)->pluck('id');

        $lodgeNumbers = Club::whereIn('id', $clubIds)->where('lodge_number', self::OLD_LODGE_NUMBER)->update(['lodge_number' => null]);
        $mottos = Club::whereIn('id', $clubIds)->where('motto', self::OLD_MOTTO)->update(['motto' => null]);
        $headings = Meeting::whereIn('club_id', $clubIds)->where('officers_year_label', self::OLD_OFFICERS_HEADING)->update(['officers_year_label' => null]);

        $this->info("Emptied {$lodgeNumbers} lodge numbers, {$mottos} mottos and {$headings} officers headings.");

        return self::SUCCESS;
    }
}
