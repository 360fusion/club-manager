<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Services\Events\EventPricing;
use App\Services\Payment\PlatformFees;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The lodge's own ways of being paid, set up once and then switched on for each event.
 */
class PaymentOptionsController extends Controller
{
    public function index(Request $request, string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Inertia::render('Admin/PaymentOptions/Index', [
            'club' => ['name' => $club->name, 'slug' => $club->slug],
            'methods' => ClubPaymentMethod::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get()->map(fn (ClubPaymentMethod $m) => $this->present($m))->values(),
            // Pre-fill a first bank transfer option from the details the club already keeps.
            'stripeWebhookUrl' => route('webhooks.stripe.club', ['clubId' => $club->id]),
            'paypalWebhookUrl' => route('webhooks.paypal.club', ['clubId' => $club->id]),
            'onlinePaymentsLive' => (bool) config('events.online_payments'),
            'platform' => $this->platform($club),
            'returnTo' => $this->returnTo($request, $club),
            'bankCodeLabel' => Currencies::bankCodeLabel($club->currencyCode()),
            'bankDefaults' => [
                'account_name' => $club->name,
                'sort_code' => $club->settings['bank_sort_code'] ?? '',
                'account_number' => $club->settings['bank_account_number'] ?? '',
            ],
        ]);
    }

    public function store(Request $request, string $clubSlug, EventPricing $pricing): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $method = new ClubPaymentMethod(['club_id' => $club->id, 'sort_order' => ClubPaymentMethod::where('club_id', $club->id)->count()]);

        $note = $this->save($request, $method, $pricing);

        return redirect()->back()->with('success', $note ?? 'Payment option added.');
    }

    public function update(Request $request, string $clubSlug, int $id, EventPricing $pricing): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $method = ClubPaymentMethod::where('club_id', $club->id)->findOrFail($id);

        $note = $this->save($request, $method, $pricing);

        return redirect()->back()->with('success', $note ?? 'Payment option saved.');
    }

    /**
     * Switch an option on or off for the whole lodge. Switching on adds it to every upcoming event that does not have it yet.
     */
    public function toggle(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $method = ClubPaymentMethod::where('club_id', $club->id)->findOrFail($id);
        $on = (bool) $request->validate(['is_active' => 'required|boolean'])['is_active'];

        if ($on && ! $method->hasCompleteDetails()) {
            throw ValidationException::withMessages(['option_'.$method->id => $method->missingDetailsMessage()]);
        }

        $method->update(['is_active' => $on]);
        $added = $on ? $method->offerOnUpcomingEvents() : 0;

        return redirect()->back()->with('success', $on
            ? $method->label.' is on'.($added > 0 ? " and now offered on {$added} upcoming ".Str::plural('event', $added) : '').'.'
            : $method->label.' is off. It is no longer offered on any event.');
    }

    /**
     * The order options are shown to people booking.
     */
    public function reorder(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate(['ids' => 'required|array|min:1|max:50', 'ids.*' => 'integer|min:1|max:4294967295']);

        $owned = ClubPaymentMethod::where('club_id', $club->id)->whereIn('id', $validated['ids'])->pluck('id')->all();

        foreach (array_values(array_unique($validated['ids'])) as $position => $methodId) {
            if (in_array($methodId, $owned, true)) {
                ClubPaymentMethod::where('club_id', $club->id)->whereKey($methodId)->update(['sort_order' => $position]);
            }
        }

        return redirect()->back();
    }

    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $method = ClubPaymentMethod::where('club_id', $club->id)->findOrFail($id);

        // Bookings that used it keep their history; the method itself is only switched off if it was used.
        if ($method->paymentRegistrations()->exists()) {
            $method->update(['is_active' => false]);

            return redirect()->back()->with('success', 'This option was used by bookings, so it has been switched off instead of deleted.');
        }

        $method->delete();

        return redirect()->back()->with('success', 'Payment option removed.');
    }

    /**
     * @return string|null a message to show instead of the usual one
     */
    private function save(Request $request, ClubPaymentMethod $method, EventPricing $pricing): ?string
    {
        $type = $method->exists ? $method->type : $request->input('type');

        $validated = $request->validate([
            'type' => ['required', Rule::in(ClubPaymentMethod::TYPES)],
            'label' => 'required|string|max:100',
            'instructions' => 'nullable|string|max:2000',
            'default_adjustment_kind' => 'required|in:none,discount,fee',
            'default_adjustment_mode' => 'required|in:percent,fixed',
            'default_adjustment_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'default_adjustment_scope' => 'required|in:per_person,per_booking',
            'due_days' => 'nullable|integer|min:0|max:365',
            'due_basis' => 'nullable|in:before_event,after_booking',
            'is_active' => 'boolean',
            'config' => 'nullable|array|max:10',
        ]);

        $request->validate([
            'config.account_name' => 'nullable|string|max:150',
            'config.bank_name' => 'nullable|string|max:150',
            'config.sort_code' => 'nullable|string|max:20',
            'config.iban' => 'nullable|string|max:40',
            'config.account_number' => 'nullable|string|max:34',
            'config.reference_prefix' => 'nullable|string|max:20|regex:/^[A-Za-z0-9-]*$/',
            'config.stripe_publishable_key' => 'nullable|string|max:255',
            'config.stripe_secret_key' => 'nullable|string|max:500',
            'config.stripe_webhook_secret' => 'nullable|string|max:500',
            'config.stripe_mode' => 'nullable|in:keys,connect',
            'config.paypal_client_id' => 'nullable|string|max:255',
            'config.paypal_client_secret' => 'nullable|string|max:500',
            'config.paypal_webhook_id' => 'nullable|string|max:100',
            'config.paypal_mode' => 'nullable|in:sandbox,live',
        ]);

        if ($method->exists && $validated['type'] !== $type) {
            throw ValidationException::withMessages(['type' => 'The type of an existing option cannot be changed.']);
        }

        $method->type = $validated['type'];
        $pricing->assertAllowed($method, ['kind' => $validated['default_adjustment_kind']], 'default_adjustment_kind');

        $method->fill([
            'label' => $validated['label'],
            'instructions' => $validated['instructions'] ?? null,
            'default_adjustment_kind' => $validated['default_adjustment_kind'],
            'default_adjustment_mode' => $validated['default_adjustment_mode'],
            'default_adjustment_amount' => $validated['default_adjustment_kind'] === 'none' ? 0 : ($validated['default_adjustment_amount'] ?? 0),
            'default_adjustment_scope' => $validated['default_adjustment_scope'],
            'due_days' => $validated['due_days'] ?? null,
            'due_basis' => $validated['due_basis'] ?? 'before_event',
            'is_active' => $validated['is_active'] ?? true,
            'config' => $this->config($method, (array) $request->input('config', [])),
        ])->save();

        // An option that cannot work yet stays off, so people are never offered a way to pay that is not set up.
        if ($method->is_active && ! $method->hasCompleteDetails()) {
            $method->update(['is_active' => false]);

            return "Saved, but {$method->label} stays off until it is complete. ".$method->missingDetailsMessage();
        }

        $added = $method->is_active ? $method->offerOnUpcomingEvents() : 0;

        return $added > 0 ? "Saved. {$method->label} is now offered on {$added} upcoming ".Str::plural('event', $added).'.' : null;
    }

    /**
     * Where the "back" button goes: only a page inside this club's own admin, never another site.
     */
    private function returnTo(Request $request, Club $club): ?string
    {
        $path = $request->query('return');

        if (! is_string($path) || strlen($path) > 300 || ! preg_match('#^/'.preg_quote($club->slug, '#').'/admin/[A-Za-z0-9/_\-.?=&%]*$#', $path) || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /**
     * Whether this lodge can connect Stripe to the platform, where its account stands, and the fee it would agree to.
     *
     * @return array<string, mixed>
     */
    private function platform(Club $club): array
    {
        $enabled = (bool) config('platform_payments.enabled') && ! empty(config('platform_payments.stripe_secret'));
        $account = $club->platformAccount;
        $active = $account && $account->disconnected_at === null ? $account : null;

        return [
            'enabled' => $enabled,
            'can_connect_existing' => $enabled && ! empty(config('platform_payments.connect_client_id')),
            'fee' => app(PlatformFees::class)->describe($active, $club->currencySymbol()),
            'terms_url' => config('platform_payments.terms_url'),
            'account' => $active ? [
                'type' => $active->type,
                'details_submitted' => $active->details_submitted,
                'charges_enabled' => $active->charges_enabled,
                'payouts_enabled' => $active->payouts_enabled,
                'requirements' => $active->requirements ?? [],
                'terms_accepted' => $active->terms_accepted_at !== null,
                'ready' => $active->canTakePayments(),
            ] : null,
        ];
    }

    /**
     * Keep only the settings each type uses. Secrets are write-only: leaving one blank keeps the saved value.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function config(ClubPaymentMethod $method, array $input): ?array
    {
        $current = $method->config ?? [];

        if ($method->type === ClubPaymentMethod::BANK) {
            return array_filter(array_intersect_key($input, array_flip(['account_name', 'bank_name', 'sort_code', 'account_number', 'iban', 'reference_prefix'])), fn ($v) => filled($v)) ?: null;
        }

        if ($method->type === ClubPaymentMethod::CARD) {
            $mode = ($input['stripe_mode'] ?? ($current['stripe_mode'] ?? 'keys')) === 'connect' ? 'connect' : 'keys';

            // Through the platform there are no keys to keep: the connected account takes their place.
            if ($mode === 'connect') {
                return ['stripe_mode' => 'connect'];
            }

            $config = ['stripe_mode' => 'keys', 'stripe_publishable_key' => $input['stripe_publishable_key'] ?? ($current['stripe_publishable_key'] ?? null)];

            foreach (['stripe_secret_key', 'stripe_webhook_secret'] as $secret) {
                $config[$secret] = filled($input[$secret] ?? null) ? $input[$secret] : ($current[$secret] ?? null);
            }

            return array_filter($config, fn ($v) => filled($v)) ?: null;
        }

        if ($method->type === ClubPaymentMethod::PAYPAL) {
            $config = [
                'paypal_client_id' => $input['paypal_client_id'] ?? ($current['paypal_client_id'] ?? null),
                'paypal_webhook_id' => $input['paypal_webhook_id'] ?? ($current['paypal_webhook_id'] ?? null),
                'paypal_mode' => $input['paypal_mode'] ?? ($current['paypal_mode'] ?? 'live'),
                'paypal_client_secret' => filled($input['paypal_client_secret'] ?? null) ? $input['paypal_client_secret'] : ($current['paypal_client_secret'] ?? null),
            ];

            return array_filter($config, fn ($v) => filled($v)) ?: null;
        }

        return null;
    }

    /**
     * What the admin page needs. Secret keys are never sent, only whether they are saved.
     *
     * @return array<string, mixed>
     */
    private function present(ClubPaymentMethod $m): array
    {
        $config = $m->config ?? [];

        return [
            'id' => $m->id,
            'type' => $m->type,
            'label' => $m->label,
            'instructions' => $m->instructions,
            'default_adjustment_kind' => $m->default_adjustment_kind,
            'default_adjustment_mode' => $m->default_adjustment_mode,
            'default_adjustment_amount' => $m->default_adjustment_amount,
            'default_adjustment_scope' => $m->default_adjustment_scope,
            'due_days' => $m->due_days,
            'due_basis' => $m->due_basis,
            'is_active' => $m->is_active,
            'config' => [
                ...$m->bankDetails(),
                'stripe_publishable_key' => $config['stripe_publishable_key'] ?? null,
                'stripe_mode' => $config['stripe_mode'] ?? 'keys',
                'paypal_client_id' => $config['paypal_client_id'] ?? null,
                'paypal_webhook_id' => $config['paypal_webhook_id'] ?? null,
                'paypal_mode' => $config['paypal_mode'] ?? 'live',
            ],
            'has_paypal_secret' => ! empty($config['paypal_client_secret']),
            'ready_for_paypal' => $m->isReadyForPayPal(),
            'has_stripe_secret_key' => ! empty($config['stripe_secret_key']),
            'has_stripe_webhook_secret' => ! empty($config['stripe_webhook_secret']),
            'ready_for_cards' => $m->isReadyForCards(),
            'complete' => $m->hasCompleteDetails(),
        ];
    }
}
