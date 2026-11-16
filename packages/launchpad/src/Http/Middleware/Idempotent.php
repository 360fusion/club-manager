<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;

/**
 * `launchpad.idempotent`, after `launchpad.api`. A write that carries an `Idempotency-Key` header is done once per workspace: the same key with the
 * same request within a day gets the first answer again (with `Idempotent-Replayed: true`) instead of doing it twice, so a client may retry after a
 * timeout. The same key with a different request is a 422, one still running a 409, and a 5xx is not kept, so it can be tried again.
 * shortcut: the answer is kept in the cache (so it goes if the cache is flushed) and only a plain body is kept, not a streamed one; move to a table when a replay must survive that.
 */
class Idempotent
{
    private const KEEP_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $workspace = app(CurrentWorkspace::class)->workspace;

        if ($key === null || $workspace === null || $request->isMethodSafe()) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9_.:-]{1,255}$/', $key)) {
            return $this->fail('The Idempotency-Key must be 1 to 255 letters, numbers or _ . : -', 400);
        }

        $id = "launchpad.idempotency.{$workspace->id}.".hash('sha256', $key);
        $fingerprint = hash('sha256', $request->method().' '.$request->fullUrl()."\n".$request->getContent());

        // add() is the lock: only one request can be the first
        if (! Cache::add($id, ['state' => 'running', 'fingerprint' => $fingerprint], 60)) {
            $seen = (array) Cache::get($id);

            return match (true) {
                ($seen['fingerprint'] ?? null) !== $fingerprint => $this->fail('This Idempotency-Key was used with a different request.', 422),
                ($seen['state'] ?? null) !== 'done' => $this->fail('A request with this Idempotency-Key is still being processed.', 409),
                default => response($seen['body'], $seen['status'], ['Content-Type' => $seen['type'], 'Idempotent-Replayed' => 'true']),
            };
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            Cache::forget($id);

            return $response;
        }

        Cache::put($id, [
            'state' => 'done', 'fingerprint' => $fingerprint, 'status' => $response->getStatusCode(),
            'body' => $response->getContent(), 'type' => $response->headers->get('Content-Type'),
        ], self::KEEP_SECONDS);

        return $response;
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
