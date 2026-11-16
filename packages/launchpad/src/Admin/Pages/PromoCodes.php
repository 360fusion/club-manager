<?php

namespace Launchpad\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Billing\Gateway;
use Launchpad\Billing\PromoCode;
use Launchpad\Modules;
use Stripe\Exception\AuthenticationException;
use Throwable;

/**
 * Promotion codes in Stripe, made and turned off here instead of in Stripe's dashboard. The discount itself lives in Stripe (a coupon and a
 * code for it); a customer types the code on Stripe's checkout page, or arrives with `/pricing?promo=CODE` and has it applied. Turning a code
 * off stops new uses and touches nothing already bought. Every change is in the admin log.
 */
class PromoCodes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'promo-codes';

    protected static ?string $title = 'Promo codes';

    protected string $view = 'launchpad::admin.promo-codes';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var list<array{id: string, code: string, discount: string, used: string, expires: string, active: bool}> */
    public array $codes = [];

    public ?string $error = null;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-ticket';
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
        $this->form->fill(['kind' => 'percent', 'duration' => 'once']);
        $this->load();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('New promo code')->columns(3)->schema([
                TextInput::make('code')->required()->rule('regex:/^[A-Za-z0-9-]{3,40}$/')
                    ->helperText('Letters, numbers and dashes. Customers type it at checkout, or follow /pricing?promo=CODE.')
                    ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state)),
                Select::make('kind')->options(['percent' => 'Percent off', 'amount' => 'Fixed amount off'])->required()->selectablePlaceholder(false)->live(),
                TextInput::make('value')->required()->integer()->minValue(1)->maxValue(fn (Get $get) => $get('kind') === 'percent' ? 100 : null)
                    ->helperText(fn (Get $get) => $get('kind') === 'amount' ? 'In the smallest unit (pence or cents), like the plan prices.' : 'Percent, 1 to 100.'),
                Select::make('duration')->label('For how long')->options(['once' => 'The first payment', 'repeating' => 'A number of months', 'forever' => 'For as long as they subscribe'])->required()->selectablePlaceholder(false)->live(),
                TextInput::make('months')->label('How many months')->integer()->minValue(1)->maxValue(36)
                    ->visible(fn (Get $get) => $get('duration') === 'repeating')->required(fn (Get $get) => $get('duration') === 'repeating'),
                TextInput::make('max')->label('Most times it can be used')->integer()->minValue(1)->helperText('Blank for no limit.'),
                DatePicker::make('expires')->label('Stops working after')->minDate(today())->helperText('Blank for never.'),
            ]),
        ])->statePath('data');
    }

    public function addAction(): Action
    {
        return Action::make('add')->label('Make promo code')->action(fn () => $this->add());
    }

    public function add(): void
    {
        $state = $this->form->getState();
        $percent = $state['kind'] === 'percent';
        $discount = ($percent ? ['percent_off' => (float) $state['value']] : ['amount_off' => (int) $state['value']])
            + ['duration' => $state['duration']] + ($state['duration'] === 'repeating' ? ['duration_in_months' => (int) $state['months']] : []);

        try {
            app(Gateway::class)->createPromotionCode($state['code'], $discount, filled($state['max'] ?? null) ? (int) $state['max'] : null, filled($state['expires'] ?? null) ? Carbon::parse($state['expires'])->endOfDay() : null);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Stripe did not make the code')->body($e->getMessage())->send();

            return;
        }

        AdminLog::record('promo_code.created', null, "Made promo code {$state['code']}", [], [
            'discount' => PromoCode::describe($discount['percent_off'] ?? null, $discount['amount_off'] ?? null, $discount['duration'], $discount['duration_in_months'] ?? null),
        ]);

        $this->form->fill(['kind' => 'percent', 'duration' => 'once']);
        $this->load();
        Notification::make()->success()->title("{$state['code']} is ready to use")->send();
    }

    public function deactivate(string $id): void
    {
        $code = collect($this->codes)->firstWhere('id', $id);

        if ($code === null) {
            return;
        }

        try {
            app(Gateway::class)->deactivatePromotionCode($id);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Stripe did not turn it off')->body($e->getMessage())->send();

            return;
        }

        AdminLog::record('promo_code.deactivated', null, "Turned off promo code {$code['code']}");
        $this->load();
        Notification::make()->success()->title("{$code['code']} is turned off")->send();
    }

    /** What Stripe has, kept on the page so a click on a form field does not ask Stripe again */
    private function load(): void
    {
        try {
            $this->error = null;
            $this->codes = collect(app(Gateway::class)->promotionCodes())->map(fn ($c) => [
                'id' => $c['id'], 'code' => $c['code'], 'discount' => $c['discount'], 'active' => $c['active'],
                'used' => $c['redeemed'].($c['max'] !== null ? " of {$c['max']}" : ''),
                'expires' => $c['expires_at']?->toFormattedDateString() ?? 'Never',
            ])->all();
        } catch (AuthenticationException) {
            $this->codes = [];
            $this->error = 'Stripe is not set up: add STRIPE_KEY and STRIPE_SECRET to the environment.';
        } catch (Throwable $e) {
            $this->codes = [];
            $this->error = 'Stripe could not be reached: '.$e->getMessage();
        }
    }
}
