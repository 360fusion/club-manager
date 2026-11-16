<?php

namespace Launchpad\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\Setting;
use Launchpad\Workspaces\TeamAddresses;

/** Every team's pages at /w/{slug} or on its own subdomain: turned on only once the domain, the session cookie and wildcard DNS are ready */
class TeamAddressesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'team-addresses';

    protected static ?string $title = 'Team addresses';

    protected string $view = 'launchpad::admin.features';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-globe-alt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function canAccess(): bool
    {
        return config('launchpad.modules.workspaces') && StaffAccess::allowed('manage-site');
    }

    public function mount(): void
    {
        $this->form->fill(['subdomains' => (bool) Setting::get('team_subdomains', false)]);
    }

    public function form(Schema $schema): Schema
    {
        $domain = TeamAddresses::domain();
        $problems = TeamAddresses::problems();
        $forced = config('launchpad.workspaces.subdomains');

        return $schema->components([
            Section::make('Where teams\' pages are')->schema([
                Text::make($domain ? 'A team called Acme has its pages at '.(parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https')."://acme.{$domain} with subdomains on, and at /w/acme with them off. Old /w/ links go to the subdomain."
                    : 'Teams\' pages are at /w/{team}.'),
                Text::make($problems === [] ? 'Ready: the domain, the session cookie and wildcard DNS are all set.' : 'Before subdomains can be turned on: '.implode(' ', $problems))
                    ->color($problems === [] ? 'success' : 'warning'),
                Text::make($forced === null ? '' : 'Config sets this (LAUNCHPAD_TEAM_SUBDOMAINS), so the switch here has no effect.')->visible($forced !== null),
                Toggle::make('subdomains')->label('Each team on its own subdomain')
                    // turning it off is always allowed; turning it on waits for the setup
                    ->disabled(fn (): bool => $problems !== [] && ! Setting::get('team_subdomains', false)),
            ]),
        ])->statePath('data');
    }

    public function saveAction(): Action
    {
        return Action::make('save')->label('Save')->requiresConfirmation()
            ->modalDescription('Every team\'s address changes at once. People who are signed in stay signed in; links to the old addresses keep working.')
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        $on = (bool) ($this->form->getState()['subdomains'] ?? false);

        if ($on === (bool) Setting::get('team_subdomains', false)) {
            Notification::make()->success()->title('Nothing changed')->send();

            return;
        }

        if ($on && TeamAddresses::problems() !== []) {
            Notification::make()->danger()->title('Not ready yet')->body(implode(' ', TeamAddresses::problems()))->send();

            return;
        }

        $on ? Setting::set('team_subdomains', true) : Setting::unset('team_subdomains');
        // the team routes are registered for one mode or the other
        app()->routesAreCached() && Artisan::call('route:clear');

        Notification::make()->success()->title($on ? 'Teams are on their subdomains' : 'Teams are back at /w/')->send();
    }
}
