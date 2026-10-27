<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User uses the Stripe and the Paddle billing traits together, with the clashing methods settled by hand. A package
 * update that adds another clashing method would fail here, not the first time someone opens the billing page.
 */
class UserBillingTraitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_be_asked_about_both_billing_providers_without_calling_either(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->subscribed('default'));
        $this->assertFalse($user->hasStripeId());
        $this->assertNull($user->customer);
        $this->assertFalse($user->onTrial());
        $this->assertCount(0, $user->subscriptions);
    }
}
