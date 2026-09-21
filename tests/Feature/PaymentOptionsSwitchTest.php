<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\User;
use App\Services\Events\EventPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentOptionsSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->treasurer = $this->join($this->club, 'treasurer');
    }

    private function join(Club $club, string $role): User
    {
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function event(string $slug, array $extra = []): Event
    {
        return Event::create($extra + ['club_id' => $this->club->id, 'title' => ucfirst($slug), 'slug' => $slug, 'starts_at' => now()->addWeeks(2), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 30]);
    }

    private function option(string $type, string $label, array $extra = []): ClubPaymentMethod
    {
        return ClubPaymentMethod::create($extra + ['club_id' => $this->club->id, 'type' => $type, 'label' => $label, 'is_active' => false]);
    }

    private function toggle(ClubPaymentMethod $method, bool $on, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->treasurer)->put(route('admin.payment_options.toggle', ['clubSlug' => 'club-a', 'id' => $method->id]), ['is_active' => $on]);
    }

    public function test_switching_an_option_on_offers_it_on_upcoming_events_but_respects_events_that_turned_it_off(): void
    {
        $open = $this->event('open');
        $unticked = $this->event('unticked');
        $past = $this->event('past', ['starts_at' => now()->subWeek(), 'status' => 'completed']);
        $cancelled = $this->event('cancelled', ['status' => 'cancelled']);
        $later = $this->option('pay_later', 'Pay later', ['due_days' => 7]);
        EventPaymentMethod::create(['event_id' => $unticked->id, 'payment_method_id' => $later->id, 'is_enabled' => false]);

        $this->toggle($later, true)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, 'now offered on 1 upcoming event'));

        $this->assertTrue($later->fresh()->is_active);
        $this->assertTrue((bool) EventPaymentMethod::where('event_id', $open->id)->where('payment_method_id', $later->id)->value('is_enabled'));
        $this->assertFalse((bool) EventPaymentMethod::where('event_id', $unticked->id)->where('payment_method_id', $later->id)->value('is_enabled'), 'an event that turned it off keeps its choice');
        $this->assertSame(0, EventPaymentMethod::whereIn('event_id', [$past->id, $cancelled->id])->count());

        $offered = fn (Event $event) => app(EventPricing::class)->enabledMethods($event)->pluck('method.label')->all();
        $this->assertSame(['Pay later'], $offered($open));
        $this->assertSame([], $offered($unticked));

        $this->toggle($later, false)->assertSessionHasNoErrors();
        $this->assertSame([], $offered($open->fresh()), 'off means no event offers it');
        $this->assertSame(1, EventPaymentMethod::where('event_id', $open->id)->count(), 'its settings on the event are kept');
    }

    public function test_an_option_that_is_not_complete_cannot_be_switched_on_and_is_never_offered(): void
    {
        $event = $this->event('open');
        $bank = $this->option('bank_transfer', 'Bank');
        $card = $this->option('card_online', 'Card');
        $paypal = $this->option('paypal', 'PayPal');

        foreach ([$bank, $card, $paypal] as $method) {
            $this->toggle($method, true)->assertSessionHasErrors('option_'.$method->id);
            $this->assertFalse($method->fresh()->is_active);
        }

        $this->assertSame(0, EventPaymentMethod::count());

        $bank->update(['config' => ['account_number' => '12345678']]);
        $this->toggle($bank->fresh(), true)->assertSessionHasNoErrors();
        $this->assertSame(['Bank'], app(EventPricing::class)->enabledMethods($event)->pluck('method.label')->all());

        $legacy = $this->option('bank_transfer', 'Legacy bank', ['is_active' => true]);
        EventPaymentMethod::create(['event_id' => $event->id, 'payment_method_id' => $legacy->id, 'is_enabled' => true]);
        $this->assertSame(['Bank'], app(EventPricing::class)->enabledMethods($event)->pluck('method.label')->all(), 'a bank option with no account is not offered');
    }

    public function test_saving_an_option_whose_details_are_missing_keeps_it_off_and_a_complete_one_is_offered_straight_away(): void
    {
        $event = $this->event('open');
        $base = ['type' => 'bank_transfer', 'label' => 'Bank transfer', 'default_adjustment_kind' => 'none', 'default_adjustment_mode' => 'fixed', 'default_adjustment_amount' => 0, 'default_adjustment_scope' => 'per_person', 'is_active' => true];

        $this->actingAs($this->treasurer)->post(route('admin.payment_options.store', ['clubSlug' => 'club-a']), $base)->assertSessionHas('success', fn ($m) => str_contains($m, 'stays off'));
        $this->assertFalse(ClubPaymentMethod::sole()->is_active);

        $this->actingAs($this->treasurer)->put(route('admin.payment_options.update', ['clubSlug' => 'club-a', 'id' => ClubPaymentMethod::sole()->id]), $base + ['config' => ['bank_name' => 'Bank of Club', 'iban' => 'GB00TEST00000000000000', 'sort_code' => '20-00-00']])->assertSessionHas('success', fn ($m) => str_contains($m, 'now offered on 1 upcoming event'));
        $method = ClubPaymentMethod::sole();
        $this->assertTrue($method->is_active);
        $this->assertSame('GB00TEST00000000000000', $method->bankDetails()['iban']);
        $this->assertSame('Sort code', $method->bankDetails()['code_label']);
        $this->assertSame(1, EventPaymentMethod::where('event_id', $event->id)->count());
    }

    public function test_the_page_lists_readiness_and_the_return_address_only_ever_points_inside_this_lodges_admin(): void
    {
        $this->option('bank_transfer', 'Bank', ['is_active' => true]);
        $page = fn (string $return) => $this->actingAs($this->treasurer)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']).'?return='.urlencode($return));

        $page('/club-a/admin/events/5/edit')->assertInertia(fn ($p) => $p->where('returnTo', '/club-a/admin/events/5/edit')->where('methods.0.complete', false)->where('bankCodeLabel', 'Sort code'));
        $page('/club-a/admin/settings?tab=payments')->assertInertia(fn ($p) => $p->where('returnTo', '/club-a/admin/settings?tab=payments'));

        foreach (['https://evil.test/club-a/admin/x', '//evil.test/club-a/admin/x', '/club-b/admin/events', '/club-a/admin/../../etc', '/club-a/admin/x\\y', 'javascript:alert(1)'] as $bad) {
            $page($bad)->assertInertia(fn ($p) => $p->where('returnTo', null));
        }
    }

    public function test_options_can_be_reordered_only_by_the_lodges_own_billing_staff(): void
    {
        $a = $this->option('pay_later', 'A', ['sort_order' => 0]);
        $b = $this->option('cash_on_door', 'B', ['sort_order' => 1]);
        $otherClub = Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $foreign = ClubPaymentMethod::create(['club_id' => $otherClub->id, 'type' => 'pay_later', 'label' => 'Foreign', 'sort_order' => 0]);
        $url = route('admin.payment_options.order', ['clubSlug' => 'club-a']);

        $this->actingAs($this->treasurer)->post($url, ['ids' => [$b->id, $a->id, $foreign->id]])->assertSessionHasNoErrors();
        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(0, $foreign->fresh()->sort_order, 'another lodge\'s option is never touched');

        $this->actingAs($this->join($this->club, 'member'))->post($url, ['ids' => [$a->id, $b->id]])->assertForbidden();
        $this->actingAs($this->join($otherClub, 'admin'))->post($url, ['ids' => [$a->id, $b->id]])->assertForbidden();
        $this->actingAs($this->join($this->club, 'member'))->put(route('admin.payment_options.toggle', ['clubSlug' => 'club-a', 'id' => $a->id]), ['is_active' => true])->assertForbidden();
        $this->assertFalse($a->fresh()->is_active);
    }

    public function test_the_event_form_lists_active_options_saving_keeps_a_switched_off_options_settings_and_duplicating_copies_them(): void
    {
        $event = $this->event('dinner');
        $on = $this->option('pay_later', 'Pay later', ['is_active' => true, 'due_days' => 7]);
        $off = $this->option('cash_on_door', 'On the night', ['is_active' => false]);
        EventPaymentMethod::create(['event_id' => $event->id, 'payment_method_id' => $on->id, 'is_enabled' => true, 'adjustment_kind' => 'fee', 'adjustment_mode' => 'fixed', 'adjustment_amount' => 3, 'adjustment_scope' => 'per_person', 'due_days' => 5]);
        EventPaymentMethod::create(['event_id' => $event->id, 'payment_method_id' => $off->id, 'is_enabled' => true, 'adjustment_kind' => 'fee', 'adjustment_mode' => 'fixed', 'adjustment_amount' => 9, 'adjustment_scope' => 'per_person']);

        $this->actingAs($this->treasurer)->get(route('admin.events.edit', ['clubSlug' => 'club-a', 'id' => $event->id]))->assertInertia(fn ($page) => $page
            ->has('clubPaymentMethods', 1)
            ->where('clubPaymentMethods.0.label', 'Pay later')
            ->where('clubPaymentMethods.0.complete', true));

        $this->actingAs($this->treasurer)->post(route('admin.events.store', ['clubSlug' => 'club-a']), [
            'id' => $event->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addWeeks(2)->format('Y-m-d\TH:i'), 'status' => 'upcoming', 'price' => 30, 'requires_payment' => true,
            'payment_methods' => [['payment_method_id' => $on->id, 'is_enabled' => true, 'adjustment_kind' => 'fee', 'adjustment_mode' => 'fixed', 'adjustment_amount' => 4, 'adjustment_scope' => 'per_person', 'due_days' => 5]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, EventPaymentMethod::where('event_id', $event->id)->count(), 'the switched-off option\'s row survives the save');
        $this->assertSame('9.00', EventPaymentMethod::where('event_id', $event->id)->where('payment_method_id', $off->id)->value('adjustment_amount'));
        $this->assertSame('4.00', EventPaymentMethod::where('event_id', $event->id)->where('payment_method_id', $on->id)->value('adjustment_amount'));

        $this->actingAs($this->treasurer)->post(route('admin.events.duplicate', ['clubSlug' => 'club-a', 'id' => $event->id]))->assertRedirect();
        $copy = Event::where('id', '!=', $event->id)->sole();
        $copied = EventPaymentMethod::where('event_id', $copy->id)->where('payment_method_id', $on->id)->sole();
        $this->assertTrue($copied->is_enabled);
        $this->assertSame('4.00', $copied->adjustment_amount);
        $this->assertSame(5, $copied->due_days);
        $this->assertSame(2, EventPaymentMethod::where('event_id', $copy->id)->count());
    }
}
