<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active', 'settings' => ['bank_sort_code' => '20-00-00', 'bank_account_number' => '12345678']]);
        $this->treasurer = $this->join('treasurer');
    }

    private function join(string $role): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function base(array $extra = []): array
    {
        return $extra + ['type' => 'bank_transfer', 'label' => 'Bank transfer', 'default_adjustment_kind' => 'none', 'default_adjustment_mode' => 'fixed', 'default_adjustment_amount' => 0, 'default_adjustment_scope' => 'per_person', 'is_active' => true];
    }

    private function create(array $extra = [])
    {
        return $this->actingAs($this->treasurer)->post(route('admin.payment_options.store', ['clubSlug' => 'club-a']), $this->base($extra));
    }

    public function test_a_treasurer_sets_up_bank_details_and_the_page_offers_the_clubs_own_as_a_start(): void
    {
        $this->create(['config' => ['account_name' => 'Club A', 'sort_code' => '20-00-00', 'account_number' => '12345678', 'reference_prefix' => 'DINNER']])->assertSessionHasNoErrors();

        $method = ClubPaymentMethod::sole();
        $this->assertSame('12345678', $method->bankDetails()['account_number']);
        $this->assertNotSame('12345678', $method->getRawOriginal('config'), 'bank and Stripe details are stored encrypted');

        $this->actingAs($this->treasurer)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']))->assertInertia(fn ($page) => $page
            ->component('Admin/PaymentOptions/Index')
            ->where('methods.0.config.sort_code', '20-00-00')
            ->where('bankDefaults.account_number', '12345678'));
    }

    public function test_stripe_secrets_are_encrypted_never_sent_back_and_kept_when_left_blank(): void
    {
        $this->create(['type' => 'card_online', 'label' => 'Pay online', 'config' => ['stripe_publishable_key' => 'pk_live_1', 'stripe_secret_key' => 'sk_live_SECRET', 'stripe_webhook_secret' => 'whsec_SECRET']])->assertSessionHasNoErrors();
        $method = ClubPaymentMethod::sole();

        $response = $this->actingAs($this->treasurer)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']));
        $response->assertInertia(fn ($page) => $page->where('methods.0.has_stripe_secret_key', true)->where('methods.0.config.stripe_publishable_key', 'pk_live_1'));
        $this->assertStringNotContainsString('SECRET', (string) $response->getContent());
        $this->assertStringNotContainsString('SECRET', $method->getRawOriginal('config'));

        $this->actingAs($this->treasurer)->put(route('admin.payment_options.update', ['clubSlug' => 'club-a', 'id' => $method->id]), $this->base(['type' => 'card_online', 'label' => 'Pay online now', 'config' => ['stripe_publishable_key' => 'pk_live_2', 'stripe_secret_key' => '', 'stripe_webhook_secret' => '']]))->assertSessionHasNoErrors();

        $fresh = $method->fresh();
        $this->assertSame('sk_live_SECRET', $fresh->config['stripe_secret_key'], 'a blank field keeps the saved secret');
        $this->assertSame('pk_live_2', $fresh->config['stripe_publishable_key']);
        $this->assertSame('Pay online now', $fresh->label);
    }

    public function test_paypal_credentials_are_encrypted_never_sent_back_kept_when_blank_and_cannot_carry_a_fee(): void
    {
        $this->create(['type' => 'paypal', 'label' => 'PayPal', 'config' => ['paypal_client_id' => 'client-1', 'paypal_client_secret' => 'PPSECRET', 'paypal_webhook_id' => 'WH-1', 'paypal_mode' => 'sandbox']])->assertSessionHasNoErrors();
        $method = ClubPaymentMethod::sole();

        $response = $this->actingAs($this->treasurer)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']));
        $response->assertInertia(fn ($page) => $page->where('methods.0.has_paypal_secret', true)->where('methods.0.ready_for_paypal', true)->where('methods.0.config.paypal_client_id', 'client-1'));
        $this->assertStringNotContainsString('PPSECRET', (string) $response->getContent());
        $this->assertStringNotContainsString('PPSECRET', $method->getRawOriginal('config'));

        $this->actingAs($this->treasurer)->put(route('admin.payment_options.update', ['clubSlug' => 'club-a', 'id' => $method->id]), $this->base(['type' => 'paypal', 'label' => 'PayPal', 'config' => ['paypal_client_id' => 'client-2', 'paypal_client_secret' => '', 'paypal_webhook_id' => 'WH-1', 'paypal_mode' => 'live']]))->assertSessionHasNoErrors();
        $this->assertSame('PPSECRET', $method->fresh()->config['paypal_client_secret']);
        $this->assertSame('client-2', $method->fresh()->config['paypal_client_id']);

        $this->create(['type' => 'paypal', 'label' => 'PayPal 2', 'default_adjustment_kind' => 'fee', 'default_adjustment_amount' => 5])->assertSessionHasErrors('default_adjustment_kind');
    }

    public function test_a_fee_is_only_allowed_on_pay_later_and_pay_on_the_night(): void
    {
        $fee = ['default_adjustment_kind' => 'fee', 'default_adjustment_amount' => 5];

        $this->create($fee + ['type' => 'card_online', 'label' => 'Online'])->assertSessionHasErrors('default_adjustment_kind');
        $this->create($fee + ['type' => 'bank_transfer', 'label' => 'Bank'])->assertSessionHasErrors('default_adjustment_kind');
        $this->assertSame(0, ClubPaymentMethod::count());

        $this->create($fee + ['type' => 'cash_on_door', 'label' => 'On the night'])->assertSessionHasNoErrors();
        $this->create(['default_adjustment_kind' => 'discount', 'default_adjustment_amount' => 3, 'type' => 'card_online', 'label' => 'Online'])->assertSessionHasNoErrors();
        $this->assertSame(2, ClubPaymentMethod::count());
    }

    public function test_only_treasurers_and_admins_can_manage_payment_options_and_only_for_their_own_club(): void
    {
        $this->create();
        $method = ClubPaymentMethod::sole();
        $member = $this->join('member');
        $coach = $this->join('coach');
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $otherAdmin = User::factory()->create();
        $other->users()->attach($otherAdmin->id, ['role' => 'admin', 'status' => 'active']);

        foreach ([$member, $coach] as $user) {
            $this->actingAs($user)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']))->assertForbidden();
            $this->actingAs($user)->post(route('admin.payment_options.store', ['clubSlug' => 'club-a']), $this->base())->assertForbidden();
        }

        $this->actingAs($otherAdmin)->get(route('admin.payment_options.index', ['clubSlug' => 'club-a']))->assertForbidden();
        $this->actingAs($otherAdmin)->put(route('admin.payment_options.update', ['clubSlug' => 'club-b', 'id' => $method->id]), $this->base())->assertNotFound();
        $this->actingAs($otherAdmin)->delete(route('admin.payment_options.destroy', ['clubSlug' => 'club-b', 'id' => $method->id]))->assertNotFound();
        $this->assertSame(1, ClubPaymentMethod::count());
    }

    public function test_an_options_type_cannot_change_and_an_unused_option_can_be_deleted(): void
    {
        $this->create();
        $method = ClubPaymentMethod::sole();

        $this->actingAs($this->treasurer)->put(route('admin.payment_options.update', ['clubSlug' => 'club-a', 'id' => $method->id]), $this->base(['type' => 'cash_on_door']))->assertSessionHasErrors('type');
        $this->assertSame('bank_transfer', $method->fresh()->type);

        $this->actingAs($this->treasurer)->delete(route('admin.payment_options.destroy', ['clubSlug' => 'club-a', 'id' => $method->id]))->assertRedirect();
        $this->assertSame(0, ClubPaymentMethod::count());
    }
}
