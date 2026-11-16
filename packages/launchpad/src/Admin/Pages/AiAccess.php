<?php

namespace Launchpad\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Launchpad\Admin\StaffAccess;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Mcp\McpAccess;
use Launchpad\Models\Setting;

/**
 * What customers' AI assistants may use through /mcp: each tool on or off for everyone (the change is in the admin log, as a setting), and,
 * to read, which plans include AI access and the plan feature a tool needs (both set in config).
 */
class AiAccess extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'ai-access';

    protected static ?string $title = 'AI access';

    protected string $view = 'launchpad::admin.features';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cpu-chip';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function canAccess(): bool
    {
        return config('launchpad.modules.api') && config('launchpad.modules.mcp', true) && StaffAccess::allowed('manage-features');
    }

    public function mount(): void
    {
        $off = McpAccess::off();
        $this->form->fill(collect(McpAccess::tools())->mapWithKeys(fn ($tool) => [self::field(McpAccess::name($tool)) => ! in_array(McpAccess::name($tool), $off, true)])->all());
    }

    private static function field(string $name): string
    {
        return 'tool__'.str_replace('-', '_', $name);
    }

    public function form(Schema $schema): Schema
    {
        $feature = config('launchpad.mcp.feature');
        $plans = collect(app(PlanCatalog::class)->all())->filter(fn ($plan) => blank($feature) || in_array($feature, $plan->features, true))->pluck('name')->implode(', ');

        $toggles = collect(McpAccess::tools())->map(function (string $tool) {
            $name = McpAccess::name($tool);
            $needs = McpAccess::featureOf($name);

            return Toggle::make(self::field($name))->label($name)
                ->helperText(app($tool)->description().($needs ? " Only on plans with the feature “{$needs}”." : ''));
        })->all();

        return $schema->components([
            Section::make('Who has AI access')->description(blank($feature)
                ? 'Every team, with an API key that can read. To make it part of some plans, set launchpad.mcp.feature to a plan feature.'
                : "Teams on plans with the feature “{$feature}”: ".($plans ?: 'none').'. Choose the plans on the Plan limits page.'),
            Section::make('Tools')->description('A tool that is off is gone for every team\'s assistant at once.')->schema($toggles)->columns(2),
        ])->statePath('data');
    }

    public function saveAction(): Action
    {
        return Action::make('save')->label('Save')->action(fn () => $this->save());
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $off = collect(McpAccess::tools())->map(fn ($tool) => McpAccess::name($tool))->reject(fn ($name) => $state[self::field($name)] ?? true)->values()->all();

        if ($off === McpAccess::off()) {
            Notification::make()->success()->title('Nothing changed')->send();

            return;
        }

        $off === [] ? Setting::unset('mcp_tools_off') : Setting::set('mcp_tools_off', $off);
        Notification::make()->success()->title('Saved')->send();
    }
}
