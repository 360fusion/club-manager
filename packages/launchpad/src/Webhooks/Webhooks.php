<?php

namespace Launchpad\Webhooks;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Launchpad\Models\WebhookDelivery;
use Launchpad\Models\WebhookEndpoint;
use Launchpad\Models\Workspace;
use Launchpad\Workspaces\Role;

/**
 * Telling a customer's own systems what happened in their workspace. `Webhooks::dispatch($workspace, 'invoice.paid', [...])` queues one signed POST
 * for every active endpoint of the workspace that subscribed to the event (config launchpad.webhooks.events lists the events the settings page
 * offers; an app adds its own there). DeliverWebhook sends it, with retries.
 */
final class Webhooks
{
    /** @var (Closure(string): list<string>)|null  tests (and an app with its own resolver) say what a host resolves to */
    private static ?Closure $resolver = null;

    /** @param  array<string, mixed>  $payload  @return int how many endpoints it was queued for */
    public static function dispatch(Workspace $workspace, string $event, array $payload): int
    {
        $count = 0;

        if (! config('launchpad.modules.webhooks')) {
            return 0;
        }

        foreach (WebhookEndpoint::where(['workspace_id' => $workspace->id, 'active' => true])->get() as $endpoint) {
            if ($endpoint->wants($event)) {
                self::queue($endpoint, $event, $payload);
                $count++;
            }
        }

        return $count;
    }

    /** Removes a workspace's endpoints and what was sent to them (done here as well as by the foreign keys, so it holds where they are not enforced) */
    public static function forget(Workspace $workspace): void
    {
        $ids = WebhookEndpoint::where('workspace_id', $workspace->id)->pluck('id');
        WebhookDelivery::whereIn('endpoint_id', $ids)->delete();
        WebhookEndpoint::whereIn('id', $ids)->delete();
    }

    /** What member.joined and member.removed carry */
    public static function memberPayload(Workspace $workspace, Model $user, ?Role $role, ?string $customRole = null): array
    {
        return ['workspace' => $workspace->slug, 'user' => ['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email], 'role' => $role?->value, 'custom_role' => $customRole];
    }

    /** One delivery to one endpoint, whatever it subscribed to (the "Send test event" button) */
    public static function queue(WebhookEndpoint $endpoint, string $event, array $payload): void
    {
        $delivery = $endpoint->deliveries()->create(['uuid' => (string) Str::uuid(), 'event' => $event, 'payload' => $payload, 'next_attempt_at' => now()]);

        // after the request's transaction, or the queue could run it before what it reports is saved
        DeliverWebhook::dispatch($delivery->id)->afterCommit();
    }

    /** The header a receiver checks: HMAC-SHA256 over "<timestamp>.<body>" with the endpoint's secret, in hex */
    public static function sign(int $timestamp, string $body, string $secret): string
    {
        return "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /** Seconds to wait before attempt number $attempts + 1: 30 s, 1 min, 2 min ... at most an hour */
    public static function backoff(int $attempts): int
    {
        return min(3600, 30 * 2 ** max(0, $attempts - 1));
    }

    /** Why the address may not be used, or null when it may */
    public static function urlProblem(string $url): ?string
    {
        try {
            self::guard($url);

            return null;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Checks an address the customer typed before anything is sent to it: https (http only on a local machine), and a host that resolves only to
     * public addresses, never this server, a private network or a cloud metadata address. Called when it is saved and again at every send,
     * because DNS can change between the two.
     *
     * @return array{host: string, port: int, ips: list<string>}
     *
     * @throws InvalidArgumentException
     */
    public static function guard(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if (! in_array($scheme, ['https', 'http'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Enter a full https address, such as https://example.com/hooks.');
        }

        if ($scheme === 'http' && ! app()->environment('local')) {
            throw new InvalidArgumentException('The address must start with https://.');
        }

        $ips = self::addresses($host);

        if ($ips === []) {
            throw new InvalidArgumentException('That address could not be found.');
        }

        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new InvalidArgumentException('That address points to a private or internal network, which cannot be used.');
            }
        }

        return ['host' => $host, 'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), 'ips' => $ips];
    }

    /** @param  (Closure(string): list<string>)|null  $resolver */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** @return list<string> */
    private static function addresses(string $host): array
    {
        if (self::$resolver) {
            return (self::$resolver)($host);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips ?: (@gethostbynamel($host) ?: [])));
    }

    private static function isPublic(string $ip): bool
    {
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $mapped)) {
            $ip = $mapped[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // 100.64.0.0/10, the shared address space of carriers and some clouds
        return ! (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (ip2long($ip) & 0xFFC00000) === 0x64400000);
    }
}
