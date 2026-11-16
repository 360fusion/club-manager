<?php

namespace Launchpad\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Laravel\Pennant\Feature;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Features\Flags;
use Launchpad\Models\Setting;
use Launchpad\Models\Workspace;

/**
 * Switch the features listed in config launchpad.features (Laravel Pennant), for everyone and for single workspaces. Everyone: On and Off are kept for
 * workspaces not yet worked out as well; Default goes back to the flag's own rules for all, which also clears what was set for single workspaces.
 * One workspace: On, Off, or Default (worked out afresh). Global changes are in the admin log as settings; a workspace's as feature.override.
 */
class Features extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'features';

    protected static ?string $title = 'Features';

    protected string $view = 'launchpad::admin.features';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-flag';
    }

    public static function canAccess(): bool
    {
        return StaffAccess::allowed('manage-features') && Flags::all() !== [];
    }

    public function mount(): void
    {
        $this->form->fill($this->current());
    }

    public static function field(string $feature): string
    {
        return "global__{$feature}";
    }

    public function form(Schema $schema): Schema
    {
        $features = collect(Flags::all())->map(fn ($flag, $key) => $flag['label'] ?? $key)->all();
        $global = [];

        foreach ($features as $key => $label) {
            $global[] = Select::make(self::field($key))->label($label)->options(['default' => 'Default (its plans and rollout)', 'on' => 'On for everyone', 'off' => 'Off for everyone'])->selectablePlaceholder(false);
        }

        return $schema->components([
            Section::make('Everyone')->description('Default clears what was set for single workspaces below.')->schema($global)->columns(2),
            Section::make('One workspace')->description('Pick all three and save. Default works it out afresh from the feature\'s own rules.')->columns(3)->schema([
                Select::make('workspace_id')->label('Workspace')->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Workspace::where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")->limit(20)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => Workspace::find($value)?->name),
                Select::make('feature')->label('Feature')->options($features),
                Select::make('state')->label('State')->options(['on' => 'On', 'off' => 'Off', 'default' => 'Default']),
            ]),
        ])->statePath('data');
    }

    public function saveAction(): Action
    {
        return Action::make('save')->label('Save features')->action(fn () => $this->save());
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = 0;

        foreach (array_keys(Flags::all()) as $key) {
            $choice = $state[self::field($key)] ?? 'default';
            $before = Setting::get("feature_{$key}");

            if ($choice === 'default' && $before !== null) {
                Setting::unset("feature_{$key}");
                Feature::purge($key);
                $changed++;
            } elseif ($choice !== 'default' && $before !== $choice) {
                Setting::set("feature_{$key}", $choice);
                $choice === 'on' ? Feature::activateForEveryone($key) : Feature::deactivateForEveryone($key);
                $changed++;
            }
        }

        $changed += $this->saveWorkspace($state);

        $this->form->fill($this->current());

        Notification::make()->success()->title($changed === 0 ? 'Nothing changed' : "{$changed} change(s) saved")->send();
    }

    /** @param  array<string, mixed>  $state */
    private function saveWorkspace(array $state): int
    {
        $workspace = Workspace::find($state['workspace_id'] ?? 0);
        $feature = $state['feature'] ?? null;
        $choice = $state['state'] ?? null;

        if (! $workspace || ! isset(Flags::all()[$feature]) || ! in_array($choice, ['on', 'off', 'default'], true)) {
            return 0;
        }

        $scoped = Feature::for($workspace);
        match ($choice) {
            'on' => $scoped->activate($feature),
            'off' => $scoped->deactivate($feature),
            'default' => $scoped->forget($feature),
        };

        AdminLog::record('feature.override', $workspace, "{$feature} {$choice}", [], ['state' => $choice]);

        return 1;
    }

    /** @return array<string, mixed> */
    private function current(): array
    {
        $state = [];

        foreach (array_keys(Flags::all()) as $key) {
            $state[self::field($key)] = in_array($set = Setting::get("feature_{$key}"), ['on', 'off'], true) ? $set : 'default';
        }

        return $state;
    }
}
