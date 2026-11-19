<?php

namespace App\Listeners;

use App\Models\PushDevice;
use Illuminate\Auth\Events\Logout;

/** Signing out of the app stops that phone getting the person's notifications. */
class ForgetPushDeviceOnLogout
{
    public function handle(Logout $event): void
    {
        $token = request()->hasSession() ? request()->session()->get('push_token') : null;

        if (is_string($token)) {
            PushDevice::where('user_id', $event->user->getAuthIdentifier())->where('token', $token)->delete();
        }
    }
}
