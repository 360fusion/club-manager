<?php

namespace App\Services\Payment;

use App\Models\Club;
use InvalidArgumentException;

class PaymentManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function __construct()
    {
        $this->gateways['stripe'] = new StripePaymentGateway;
    }

    /**
     * Get gateway instance by driver name.
     */
    public function driver(string $driver = 'stripe'): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$driver])) {
            throw new InvalidArgumentException("Unsupported payment driver [{$driver}].");
        }

        return $this->gateways[$driver];
    }

    /**
     * Get preferred payment gateway for a given club.
     */
    public function forClub(Club $club): PaymentGatewayInterface
    {
        return $this->driver('stripe');
    }
}
