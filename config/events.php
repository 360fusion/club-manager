<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Online card payments
    |--------------------------------------------------------------------------
    |
    | Lodges can set up a "Pay online" option, but it is only offered to people
    | booking once card payments are switched on. Leave off until Stripe
    | checkout is live, so nobody can choose a way to pay that does not work yet.
    |
    */

    'online_payments' => (bool) env('EVENTS_ONLINE_PAYMENTS', false),

];
