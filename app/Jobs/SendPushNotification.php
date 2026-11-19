<?php

namespace App\Jobs;

use App\Models\PushDevice;
use App\Services\Push\PushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one push notification to every phone a person has the app on, and forgets phones
 * whose token Apple or Google says is no longer in use.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $userId, public string $title, public string $body, public ?string $url = null)
    {
        $this->onQueue('transactional');
    }

    public function handle(PushSender $sender): void
    {
        PushDevice::where('user_id', $this->userId)->get()->each(function (PushDevice $device) use ($sender) {
            if ($sender->send($device, $this->title, $this->body, $this->url) === PushSender::GONE) {
                $device->delete();
            }
        });
    }
}
