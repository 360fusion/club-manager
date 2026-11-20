<?php

namespace Tests\Feature;

use App\Support\HealthChecks;
use App\Support\OpsAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Launchpad\Health\Heartbeat;
use RuntimeException;
use Tests\TestCase;

class OpsMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_health_check_fails_until_the_scheduler_and_a_queue_worker_have_been_seen(): void
    {
        $this->get('/up')->assertOk();

        $this->app['env'] = 'production';

        try {
            HealthChecks::assertLive();
            $this->fail('A live site with no scheduler should not pass the health check.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('scheduler', $e->getMessage());
        }

        Heartbeat::beat('scheduler');
        $this->expectExceptionMessage('queue worker');
        HealthChecks::assertLive();
    }

    public function test_a_live_site_with_both_heartbeats_is_healthy(): void
    {
        $this->app['env'] = 'production';
        Heartbeat::beat('scheduler');
        Heartbeat::beat('queue');

        HealthChecks::assertLive();
        $this->addToAssertionCount(1);
    }

    public function test_alerts_go_straight_out_and_each_kind_at_most_once_an_hour(): void
    {
        config(['app.ops_alerts_always' => true, 'app.ops_alert_emails' => 'ops@example.test, second@example.test', 'mail.default' => 'array']);
        $sent = fn () => app('mailer')->getSymfonyTransport()->messages();

        OpsAlerts::jobFailed('App\Jobs\SendNewsletterJob', new RuntimeException('SMTP down'));
        OpsAlerts::jobFailed('App\Jobs\SendNewsletterJob', new RuntimeException('SMTP still down'));
        $this->assertCount(1, $sent(), 'the same failing job is reported once an hour');

        OpsAlerts::error(new RuntimeException('Something else'));
        $this->assertCount(2, $sent());

        $first = $sent()->first()->getOriginalMessage();
        $this->assertStringContainsString('SendNewsletterJob', $first->getSubject());
        $this->assertSame(['ops@example.test', 'second@example.test'], array_map(fn ($address) => $address->getAddress(), $first->getTo()));

        $this->travel(61)->minutes();
        OpsAlerts::jobFailed('App\Jobs\SendNewsletterJob', new RuntimeException('SMTP down again'));
        $this->assertCount(3, $sent());
    }
}
