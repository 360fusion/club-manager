<?php

namespace Launchpad\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Launchpad\Admin\StaffAccess;
use Launchpad\Launchpad;

/** New accounts a day for the last 30 days */
class SignupsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return StaffAccess::allowed('view-accounts');
    }

    protected ?string $heading = 'Sign-ups, last 30 days';

    protected function getData(): array
    {
        $from = now()->subDays(29)->startOfDay();

        // shortcut: counted in PHP so it runs on every database; move to a grouped query when the table is too big to read 30 days of it
        $perDay = Launchpad::userModel()::where('created_at', '>=', $from)->pluck('created_at')->countBy(fn ($at) => $at->format('Y-m-d'));
        $days = collect(range(0, 29))->map(fn ($i) => $from->copy()->addDays($i));

        return [
            'datasets' => [['label' => 'New accounts', 'data' => $days->map(fn ($day) => $perDay->get($day->format('Y-m-d'), 0))->all()]],
            'labels' => $days->map(fn ($day) => $day->format('j M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
