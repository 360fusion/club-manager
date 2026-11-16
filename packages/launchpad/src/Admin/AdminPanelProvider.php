<?php

namespace Launchpad\Admin;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** The admin: a Filament panel on its own guard and path, for staff only, with the authenticator-app step required. */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Whether staff are asked for a code from an authenticator app. They are, except on a developer's own machine: when APP_ENV is "local"
     * AND config launchpad.admin.require_two_factor is false. Both are needed, so a wrong APP_ENV, or a .env copied from a laptop, does not
     * weaken a real server on its own.
     */
    public static function requiresTwoFactor(): bool
    {
        return ! (app()->environment('local') && config('launchpad.admin.require_two_factor') === false);
    }

    public function boot(): void
    {
        // the admin shows times in the staff's own time while the app stores UTC
        FilamentTimezone::set(fn (): string => config('launchpad.admin.timezone') ?: config('app.timezone'));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path((string) config('launchpad.admin.path', 'hq'))
            ->brandName(config('launchpad.admin.name') ?: config('launchpad.site.name').' HQ')
            ->authGuard('admin')
            ->login()
            ->multiFactorAuthentication(
                self::requiresTwoFactor() ? [AppAuthentication::make()->recoverable()->brandName((string) config('launchpad.site.name'))] : [],
                isRequired: self::requiresTwoFactor(),
            )
            ->colors(['primary' => Color::hex((string) config('launchpad.site.accent', '#4f46e5'))])
            ->pages([Dashboard::class])
            ->plugins([LaunchpadPlugin::make()])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
