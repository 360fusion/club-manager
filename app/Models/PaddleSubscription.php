<?php

namespace App\Models;

use Laravel\Paddle\Subscription as CashierPaddleSubscription;

class PaddleSubscription extends CashierPaddleSubscription
{
    protected $table = 'paddle_subscriptions';
}
