<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Services\Events\EventPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The lodge's own ways of being paid, set up once and then switched on for each event.
 */
class PaymentOptionsController extends Controller
{
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Inertia::render('Admin/PaymentOptions/Index', [
            'club' => ['name' => $club->name, 'slug' => $club->slug],
            'methods' => ClubPaymentMethod::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get()->map(fn (ClubPaymentMethod $m) => $this->present($m))->values(),
            // Pre-fill a first bank transfer option from the details the club already keeps.
            'stripeWebhookUrl' => route('webhooks.stripe.club', ['clubId' => $club->id]),
            'onlinePaymentsLive' => (bool) config('events.online_payments'),
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

        $this->save($request, $method, $pricing);

        return redirect()->back()->with('success', 'Payment option added.');
    }

    public function update(Request $request, string $clubSlug, int $id, EventPricing $pricing): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $method = ClubPaymentMethod::where('club_id', $club->id)->findOrFail($id);

        $this->save($request, $method, $pricing);

        return redirect()->back()->with('success', 'Payment option saved.');
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

    private function save(Request $request, ClubPaymentMethod $method, EventPricing $pricing): void
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
            'config.sort_code' => 'nullable|string|max:20',
            'config.account_number' => 'nullable|string|max:34',
            'config.reference_prefix' => 'nullable|string|max:20|regex:/^[A-Za-z0-9-]*$/',
            'config.stripe_publishable_key' => 'nullable|string|max:255',
            'config.stripe_secret_key' => 'nullable|string|max:500',
            'config.stripe_webhook_secret' => 'nullable|string|max:500',
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
            return array_filter(array_intersect_key($input, array_flip(['account_name', 'sort_code', 'account_number', 'reference_prefix'])), fn ($v) => filled($v)) ?: null;
        }

        if ($method->type === ClubPaymentMethod::CARD) {
            $config = ['stripe_publishable_key' => $input['stripe_publishable_key'] ?? ($current['stripe_publishable_key'] ?? null)];

            foreach (['stripe_secret_key', 'stripe_webhook_secret'] as $secret) {
                $config[$secret] = filled($input[$secret] ?? null) ? $input[$secret] : ($current[$secret] ?? null);
            }

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
            ],
            'has_stripe_secret_key' => ! empty($config['stripe_secret_key']),
            'has_stripe_webhook_secret' => ! empty($config['stripe_webhook_secret']),
            'ready_for_cards' => $m->isReadyForCards(),
        ];
    }
}
