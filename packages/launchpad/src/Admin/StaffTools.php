<?php

namespace Launchpad\Admin;

use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Pulse;
use Laravel\Telescope\Telescope;

/**
 * Telescope and Pulse, when the app has installed them. Both ask for a signed-in person on the default guard, which staff are not (they sign in
 * to the admin on a guard of their own), so they are told here that staff with `view-logs` may open them, and nobody else, in every environment.
 */
final class StaffTools
{
    /** Called once the app has booted, so it wins over the gate the packages (and a published TelescopeServiceProvider) set up themselves */
    public static function authorize(): void
    {
        $allowed = fn (): bool => StaffAccess::can(Auth::guard('admin')->user(), 'view-logs');

        class_exists(Telescope::class) && Telescope::auth($allowed);
        class_exists(Pulse::class) && Gate::define('viewPulse', fn ($user = null): bool => $allowed());
    }

    /** @return list<NavigationItem> the System group's links to the tools that are installed and switched on */
    public static function navigationItems(): array
    {
        $tools = [
            [Telescope::class, 'telescope', 'Telescope', 'heroicon-o-magnifying-glass'],
            [Pulse::class, 'pulse', 'Pulse', 'heroicon-o-chart-bar'],
        ];

        return collect($tools)
            ->filter(fn (array $tool) => class_exists($tool[0]))
            ->map(fn (array $tool) => NavigationItem::make($tool[2])
                ->group('System')
                ->icon($tool[3])
                ->url(fn () => url((string) config("{$tool[1]}.path", $tool[1])), shouldOpenInNewTab: true)
                ->visible(fn () => config("{$tool[1]}.enabled", true) && StaffAccess::allowed('view-logs')))
            ->values()
            ->all();
    }
}
