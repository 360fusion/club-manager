<?php

namespace Launchpad\Admin\Pages;

use Filament\Pages\Page;
use Launchpad\Admin\StaffAccess;
use Launchpad\Health\Checks;

/** The checks of `launchpad:doctor`, on screen. Checking Stripe itself asks Stripe, so it is a button and not on every visit. */
class SystemHealth extends Page
{
    protected static ?string $slug = 'system-health';

    protected static ?string $title = 'System health';

    protected string $view = 'launchpad::admin.system-health';

    public bool $remote = false;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-heart';
    }

    public static function canAccess(): bool
    {
        return StaffAccess::allowed('view-logs');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function checkStripe(): void
    {
        $this->remote = true;
    }

    /** @return list<array{name: string, ok: bool, detail: string}> */
    public function checks(): array
    {
        return Checks::run($this->remote, true);
    }

    /** @return array<string, string> */
    public function environment(): array
    {
        return [
            'Environment' => (string) config('app.env'),
            'Debug mode' => config('app.debug') ? 'on' : 'off',
            'Mail' => (string) config('mail.default'),
            'Queue' => (string) config('queue.default'),
            'Cache' => (string) config('cache.default'),
            'Sessions' => (string) config('session.driver'),
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
        ];
    }
}
