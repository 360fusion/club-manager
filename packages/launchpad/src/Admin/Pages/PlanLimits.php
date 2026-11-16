<?php

namespace Launchpad\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Launchpad\Admin\StaffAccess;
use Launchpad\Billing\Entitlements;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Models\Setting;
use Launchpad\Modules;

/**
 * What each plan allows, and which plans are held back as "Coming soon". A number here wins over the plan's own in config/launchpad.php
 * (shown as the field's placeholder); blank puts the config's number back. Lowering a limit takes nothing away from workspaces that are
 * over it already: they keep what they have and cannot add more. Every change is in the admin log (Setting writes it).
 */
class PlanLimits extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'plan-limits';

    protected static ?string $title = 'Plan limits';

    protected string $view = 'launchpad::admin.plan-limits';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function canAccess(): bool
    {
        return Modules::enabled('billing') && StaffAccess::allowed('manage-billing');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Billing';
    }

    public function mount(): void
    {
        $this->form->fill($this->current());
    }

    public static function field(string $plan, string $limit): string
    {
        return "limit__{$plan}__{$limit}";
    }

    public static function featuresField(string $plan): string
    {
        return "features__{$plan}";
    }

    public static function holdField(string $plan): string
    {
        return "hold__{$plan}";
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (app(PlanCatalog::class)->all() as $plan) {
            $fields = [];

            if (! $plan->isFree()) {
                $fields[] = Toggle::make(self::holdField($plan->key))->label('Coming soon (cannot be bought)');
            }

            if ($known = app(PlanCatalog::class)->knownFeatures()) {
                $default = (array) config("launchpad.billing.plans.{$plan->key}.features", []);
                $fields[] = CheckboxList::make(self::featuresField($plan->key))->label('Features')->options(array_combine($known, $known))->columns(3)->columnSpanFull()
                    ->helperText('What this plan includes (AI access, single sign-on and the app\'s own). From config: '.($default ? implode(', ', $default) : 'none').'.');
            }

            foreach ($plan->limits as $name => $default) {
                $fields[] = TextInput::make(self::field($plan->key, $name))
                    ->label(ucfirst(str_replace('_', ' ', $name)))
                    ->integer()->minValue(0)->maxValue(Entitlements::UNLIMITED)
                    ->placeholder((string) $default)
                    ->helperText("Default {$default}".($default >= Entitlements::UNLIMITED ? ' (no limit)' : '').'. Leave blank for the default; '.Entitlements::UNLIMITED.' means no limit.');
            }

            $sections[] = Section::make($plan->name)->schema($fields)->columns(2)->collapsible();
        }

        return $schema->components($sections)->statePath('data');
    }

    public function saveAction(): Action
    {
        return Action::make('save')->label('Save plan limits')->action(fn () => $this->save());
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = 0;

        foreach (app(PlanCatalog::class)->all() as $plan) {
            if (! $plan->isFree()) {
                $hold = (bool) ($state[self::holdField($plan->key)] ?? false);

                if ($hold !== (bool) Setting::get('plan_hold_'.$plan->key, false)) {
                    $hold ? Setting::set('plan_hold_'.$plan->key, true) : Setting::unset('plan_hold_'.$plan->key);
                    $changed++;
                }
            }

            if (array_key_exists(self::featuresField($plan->key), $state)) {
                $chosen = array_values(array_intersect(app(PlanCatalog::class)->knownFeatures(), (array) $state[self::featuresField($plan->key)]));
                $default = array_values(array_intersect(app(PlanCatalog::class)->knownFeatures(), (array) config("launchpad.billing.plans.{$plan->key}.features", [])));
                $before = Setting::get("plan_features_{$plan->key}");

                if ($chosen === $default && $before !== null) {
                    Setting::unset("plan_features_{$plan->key}");
                    $changed++;
                } elseif ($chosen !== $default && $before !== $chosen) {
                    Setting::set("plan_features_{$plan->key}", $chosen);
                    $changed++;
                }
            }

            foreach (array_keys($plan->limits) as $name) {
                $key = "plan_limit_{$plan->key}_{$name}";
                $value = $state[self::field($plan->key, $name)] ?? null;
                $before = Setting::get($key);

                if (blank($value)) {
                    if ($before !== null) {
                        Setting::unset($key);
                        $changed++;
                    }
                } elseif ((string) $before !== (string) $value) {
                    Setting::set($key, (int) $value);
                    $changed++;
                }
            }
        }

        app(PlanCatalog::class)->forget();
        $this->form->fill($this->current());

        Notification::make()->success()->title($changed === 0 ? 'Nothing changed' : "{$changed} change(s) saved")->send();
    }

    /** @return array<string, mixed> what the form shows now: the admin's numbers only (blank where the default is used) */
    private function current(): array
    {
        $state = [];

        foreach (app(PlanCatalog::class)->all() as $plan) {
            $state[self::holdField($plan->key)] = (bool) Setting::get('plan_hold_'.$plan->key, false);
            $state[self::featuresField($plan->key)] = $plan->features;

            foreach (array_keys($plan->limits) as $name) {
                $override = Setting::get("plan_limit_{$plan->key}_{$name}");
                $state[self::field($plan->key, $name)] = is_numeric($override) ? (int) $override : null;
            }
        }

        return $state;
    }
}
