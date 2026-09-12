<?php

namespace App\Models;

use Laravel\Paddle\SubscriptionItem as CashierPaddleSubscriptionItem;

class PaddleSubscriptionItem extends CashierPaddleSubscriptionItem
{
    protected $table = 'paddle_subscription_items';
}
