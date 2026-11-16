<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Launchpad\Models\ApiToken;
use Launchpad\Models\ApiTokenUsage;
use Launchpad\Models\Membership;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * `launchpad.api` or `launchpad.api:read`. Sanctum's guard finds the token the `Authorization: Bearer` header carries (and turns away an unknown or
 * expired one); this then needs it to be one of a workspace's (a session, or a token with no workspace, is not), makes that workspace the current
 * one for this request only, and the person who made the token the user. A token whose maker has left the workspace or closed their account stops
 * working with them. Without a good token: 401 JSON. With the ability named and not on the token: 403 JSON.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $user = Auth::guard('sanctum')->user();
        $token = $user?->currentAccessToken();
        $membership = $token instanceof ApiToken && $token->workspace_id !== null
            ? Membership::where(['workspace_id' => $token->workspace_id, 'user_id' => $user->getKey()])->with('workspace')->first()
            : null;

        if ($membership === null || (method_exists($user, 'isClosed') && $user->isClosed())) {
            return $this->fail('Unauthenticated.', 401)->header('WWW-Authenticate', 'Bearer');
        }

        if ((method_exists($user, 'isSuspended') && $user->isSuspended()) || $membership->workspace->suspended_at !== null) {
            return $this->fail('This account or team is suspended.', 403);
        }

        // before the ability check, so a refused call is counted against its token too
        $request->attributes->set('launchpad.api_token', $token);

        if ($ability !== null && ! $token->can($ability)) {
            return $this->fail('This token may not do that.', 403);
        }

        app(CurrentWorkspace::class)->set($membership->workspace, $membership);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /** After the response is sent: today's count for the token, and who called last. Nothing here may change what the caller got. */
    public function terminate(Request $request, Response $response): void
    {
        $token = $request->attributes->get('launchpad.api_token');

        if (! $token instanceof ApiToken) {
            return;
        }

        try {
            $table = DB::getTablePrefix().(new ApiTokenUsage)->getTable();
            $errors = $response->getStatusCode() >= 400 ? 1 : 0;

            // one row a day however many calls arrive together; the column is qualified because Postgres finds a bare one ambiguous
            ApiTokenUsage::upsert(
                [['token_id' => $token->getKey(), 'day' => now()->utc()->toDateString(), 'requests' => 1, 'errors' => $errors]],
                ['token_id', 'day'],
                ['requests' => DB::raw("{$table}.requests + 1"), 'errors' => DB::raw("{$table}.errors + {$errors}")],
            );

            $token->forceFill(['last_ip' => $request->ip(), 'last_user_agent' => Str::limit((string) $request->userAgent(), 255, '') ?: null])->saveQuietly();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
