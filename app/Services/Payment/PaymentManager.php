<?php

namespace App\Services\Payment;

use App\Models\Club;
use InvalidArgumentException;

class PaymentManager
{
    protected array $gateways = [];

    public function __construct()
    {
        $this->gateways['stripe'] = new StripePaymentGateway();
        $this->gateways['paddle'] = new PaddlePaymentGateway();
    }

    /**
     * Get gateway instance by driver name.
     */
    public function driver(string $driver = 'stripe'): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$driver])) {
            throw new InvalidArgumentException("Unsupported payment driver [{$driver}].");
        }

        return $this->gateways[$driver];
    }

    /**
     * Get preferred payment gateway for a given club.
     */
    public function forClub(Club $club): PaymentGatewayInterface
    {
        $preferredDriver = $club->settings['payment_provider'] ?? 'stripe';
        return $this->driver($preferredDriver);
    }
}
