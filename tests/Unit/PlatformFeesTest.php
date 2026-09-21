<?php

namespace Tests\Unit;

use App\Models\ClubPlatformAccount;
use App\Services\Payment\PlatformFees;
use Tests\TestCase;

class PlatformFeesTest extends TestCase
{
    private function fees(array $config = []): PlatformFees
    {
        config(array_merge(['platform_payments.commission_percent' => 2.0, 'platform_payments.commission_fixed' => 0.0, 'platform_payments.commission_min' => 0.0, 'platform_payments.commission_max' => 0.0], $config));

        return new PlatformFees;
    }

    public function test_the_commission_is_a_percentage_plus_a_fixed_amount_in_whole_pence(): void
    {
        $this->assertSame(200, $this->fees()->commissionMinor(10000));
        $this->assertSame(130, $this->fees(['platform_payments.commission_fixed' => 0.30])->commissionMinor(5000));
        $this->assertSame(1, $this->fees(['platform_payments.commission_percent' => 2.5])->commissionMinor(33), '0.825 rounds to 1');
    }

    public function test_it_stays_between_the_minimum_and_the_cap_and_never_exceeds_the_payment(): void
    {
        $fees = $this->fees(['platform_payments.commission_min' => 0.50, 'platform_payments.commission_max' => 3.00]);

        $this->assertSame(50, $fees->commissionMinor(1000), 'raised to the minimum');
        $this->assertSame(300, $fees->commissionMinor(1_000_000), 'held to the cap');
        $this->assertSame(40, $fees->commissionMinor(40), 'never more than the payment');
        $this->assertSame(0, $fees->commissionMinor(0));
    }

    public function test_a_lodge_can_have_its_own_rate(): void
    {
        $account = new ClubPlatformAccount(['commission_percent' => 1.0, 'commission_fixed' => 0.10]);

        $this->assertSame(60, $this->fees(['platform_payments.commission_fixed' => 0.30])->commissionMinor(5000, $account));
        $this->assertSame('1% + £0.10 of each card payment', $this->fees()->describe($account, '£'));
        $this->assertSame('2% of each card payment', $this->fees()->describe(null, '£'));
    }
}
