<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Club;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NativeAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_return_page_opens_the_app_at_the_path_keeping_what_paypal_added(): void
    {
        $this->get('/app/return?to='.urlencode('/members/club-a/events/4/paypal-return').'&token=ORDER1&PayerID=P9')
            ->assertOk()
            ->assertSee('clubmanager://open?path='.rawurlencode('/members/club-a/events/4/paypal-return?token=ORDER1&PayerID=P9'), false);
    }

    public function test_the_return_page_never_sends_the_app_to_another_site(): void
    {
        $this->get('/app/return?to='.urlencode('//evil.example/steal'))
            ->assertOk()
            ->assertSee('clubmanager://open?path='.rawurlencode('/members/dashboard'), false)
            ->assertDontSee('evil.example');
    }

    public function test_the_android_app_only_asks_for_push_once_firebase_is_set_up(): void
    {
        $user = User::factory()->create();
        $app = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 15) ClubManagerApp'];

        $this->actingAs($user)->withHeaders($app)->get(route('members.dashboard'))
            ->assertInertia(fn ($page) => $page->where('androidPush', false));

        config(['services.fcm.credentials_path' => '/secrets/fcm.json']);

        $this->actingAs($user)->withHeaders($app)->get(route('members.dashboard'))
            ->assertInertia(fn ($page) => $page->where('androidPush', true));
        $this->flushHeaders()->actingAs($user)->get(route('members.dashboard'))
            ->assertInertia(fn ($page) => $page->where('androidPush', false));
    }

    public function test_livewire_admin_pages_in_the_app_clear_the_status_bar_and_lead_back_to_the_member_area(): void
    {
        $club = Club::factory()->create(['slug' => 'club-a']);
        $admin = User::factory()->create();
        $club->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);
        $page = route('admin.club_acc.members.import', ['clubSlug' => 'club-a']);

        $this->actingAs($admin)->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone) ClubManagerApp'])->get($page)
            ->assertOk()
            ->assertSee('native-app', false)
            ->assertSee('Member area');

        $this->flushHeaders()->actingAs($admin)->get($page)->assertOk()->assertDontSee('Member area');
    }

    public function test_a_signed_in_member_registers_their_phone_for_push(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('members.push_devices.store'), ['token' => 'abc123', 'platform' => 'ios'])
            ->assertOk()
            ->assertSessionHas('push_token', 'abc123');

        $this->assertDatabaseHas('push_devices', ['user_id' => $user->id, 'token' => 'abc123', 'platform' => 'ios']);
    }

    public function test_registering_a_phone_needs_someone_signed_in(): void
    {
        $this->postJson(route('members.push_devices.store'), ['token' => 'abc123', 'platform' => 'ios'])->assertUnauthorized();

        $this->assertDatabaseCount('push_devices', 0);
    }

    public function test_the_platform_must_be_ios_or_android(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('members.push_devices.store'), ['token' => 'abc123', 'platform' => 'windows'])
            ->assertJsonValidationErrors(['platform' => 'The selected platform is invalid.']);
    }

    public function test_a_phone_token_moves_to_whoever_signs_in_on_that_phone(): void
    {
        $device = PushDevice::factory()->create(['token' => 'shared-phone']);
        $next = User::factory()->create();

        $this->actingAs($next)->postJson(route('members.push_devices.store'), ['token' => 'shared-phone', 'platform' => 'ios'])->assertOk();

        $this->assertSame($next->id, $device->fresh()->user_id);
        $this->assertDatabaseCount('push_devices', 1);
    }

    public function test_a_member_can_only_remove_their_own_phone(): void
    {
        $someoneElses = PushDevice::factory()->create(['token' => 'their-phone']);
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson(route('members.push_devices.destroy'), ['token' => 'their-phone'])->assertOk();

        $this->assertModelExists($someoneElses);
    }

    public function test_signing_out_stops_that_phone_getting_pushes(): void
    {
        $device = PushDevice::factory()->create(['token' => 'this-phone']);
        $other = PushDevice::factory()->create(['user_id' => $device->user_id, 'token' => 'their-tablet']);

        $this->actingAs($device->user)->withSession(['push_token' => 'this-phone'])->post('/logout');

        $this->assertModelMissing($device);
        $this->assertModelExists($other);
    }

    public function test_an_inbox_notification_is_pushed_to_a_member_with_the_app(): void
    {
        $club = Club::factory()->create(['name' => 'Harbour Lodge']);
        $device = PushDevice::factory()->create();
        Queue::fake([SendPushNotification::class]);

        $device->user->notify(ClubNotification::membershipApproved($club));

        $notificationId = $device->user->notifications()->sole()->id;
        Queue::assertPushed(SendPushNotification::class, fn (SendPushNotification $job) => $job->userId === $device->user_id
            && $job->title === 'Welcome to Harbour Lodge'
            && $job->url === "/members/notifications/{$notificationId}/open");
    }

    public function test_nothing_is_queued_for_a_member_without_the_app(): void
    {
        $user = User::factory()->create();
        Queue::fake([SendPushNotification::class]);

        $user->notify(ClubNotification::membershipApproved(Club::factory()->create()));

        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_an_iphone_is_sent_a_signed_push_through_apple(): void
    {
        $publicKey = $this->configureApns();
        $device = PushDevice::factory()->create(['token' => 'iphone-token']);
        Http::preventStrayRequests();
        Http::fake(['https://api.sandbox.push.apple.com/3/device/iphone-token' => Http::response()]);

        (new SendPushNotification($device->user_id, 'Dining closes Friday', 'Book by Friday.', '/members/notifications/1/open'))->handle(app(PushSender::class));

        Http::assertSent(function (Request $request) use ($publicKey) {
            [$header, $claims, $signature] = explode('.', preg_replace('/^bearer /i', '', $request->header('Authorization')[0]));

            return $request->header('apns-topic')[0] === 'uk.clubmanager.app'
                && $request['aps']['alert'] === ['title' => 'Dining closes Friday', 'body' => 'Book by Friday.']
                && $request['url'] === '/members/notifications/1/open'
                && json_decode($this->base64urlDecode($claims), true)['iss'] === 'TEAM123456'
                && openssl_verify("{$header}.{$claims}", $this->joseToDer($this->base64urlDecode($signature)), $publicKey, OPENSSL_ALGO_SHA256) === 1;
        });
        $this->assertModelExists($device);
    }

    public function test_an_iphone_apple_says_is_gone_is_forgotten(): void
    {
        $this->configureApns();
        $device = PushDevice::factory()->create(['token' => 'old-iphone']);
        Http::preventStrayRequests();
        Http::fake(['https://api.sandbox.push.apple.com/3/device/old-iphone' => Http::response(['reason' => 'Unregistered'], 410)]);

        (new SendPushNotification($device->user_id, 'Hello', 'World'))->handle(app(PushSender::class));

        $this->assertModelMissing($device);
    }

    public function test_an_android_phone_google_says_is_unregistered_is_forgotten(): void
    {
        $this->configureFcm();
        $device = PushDevice::factory()->android()->create(['token' => 'old-android']);
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test']),
            'https://fcm.googleapis.com/v1/projects/club-manager-test/messages:send' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        (new SendPushNotification($device->user_id, 'Hello', 'World', '/members/dashboard'))->handle(app(PushSender::class));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === 'old-android'
            && $request['message']['data'] === ['url' => '/members/dashboard']);
        $this->assertModelMissing($device);
    }

    public function test_nothing_is_sent_while_push_is_not_set_up(): void
    {
        $iphone = PushDevice::factory()->create();
        $android = PushDevice::factory()->android()->create(['user_id' => $iphone->user_id]);
        Http::preventStrayRequests();

        (new SendPushNotification($iphone->user_id, 'Hello', 'World'))->handle(app(PushSender::class));

        Http::assertNothingSent();
        $this->assertModelExists($iphone);
        $this->assertModelExists($android);
    }

    private function configureApns(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'apns');
        file_put_contents($path, $pem);
        config(['services.apns' => ['key_id' => 'KEY1234567', 'team_id' => 'TEAM123456', 'key_path' => $path, 'bundle_id' => 'uk.clubmanager.app', 'production' => false]]);

        return openssl_pkey_get_details($key)['key'];
    }

    private function configureFcm(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode(['project_id' => 'club-manager-test', 'client_email' => 'push@club-manager-test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']));
        config(['services.fcm.credentials_path' => $path]);
    }

    private function base64urlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }

    /** The reverse of PushSender::derToJose, so OpenSSL can check the JWT's signature. */
    private function joseToDer(string $raw): string
    {
        $integer = function (string $part): string {
            $part = ltrim($part, "\x00");
            if (ord($part[0]) & 0x80) {
                $part = "\x00".$part;
            }

            return "\x02".chr(strlen($part)).$part;
        };
        $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32, 32));

        return "\x30".chr(strlen($body)).$body;
    }
}
