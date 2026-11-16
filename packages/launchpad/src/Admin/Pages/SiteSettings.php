<?php

namespace Launchpad\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\Setting;
use Launchpad\Site\Site;

/**
 * Who the site is: the words the pages use as {{tokens}} (company, product, contact addresses, governing law) and the tagline. What is saved
 * here wins over the config; blank puts the config's value back. Every change is in the admin log.
 */
class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'site-settings';

    protected static ?string $title = 'Site settings';

    protected string $view = 'launchpad::admin.site-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function canAccess(): bool
    {
        return StaffAccess::allowed('manage-site');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public function mount(): void
    {
        $this->form->fill(collect(Site::FIELDS)->mapWithKeys(fn ($label, $key) => [$key => Setting::get('site_'.$key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $fields = [];

        foreach (Site::FIELDS as $key => $label) {
            $field = TextInput::make($key)->label($label)->maxLength(500)->placeholder(fn () => (string) config("launchpad.site.{$key}"));

            if (str_ends_with($key, '_email')) {
                $field->email();
            }

            $fields[] = $field;
        }

        return $schema->components([
            Section::make('Used on every page and in mail')->description('A pages shows these where it says {{company}}, {{support_email}} and so on. Leave a field blank to use the app\'s own setting.')->schema($fields)->columns(2),
        ])->statePath('data');
    }

    public function saveAction(): Action
    {
        return Action::make('save')->label('Save')->action(fn () => $this->save());
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (array_keys(Site::FIELDS) as $key) {
            $value = trim((string) ($state[$key] ?? ''));

            $value === '' ? Setting::unset('site_'.$key) : Setting::set('site_'.$key, $value);
        }

        Notification::make()->success()->title('Saved')->send();
    }
}
