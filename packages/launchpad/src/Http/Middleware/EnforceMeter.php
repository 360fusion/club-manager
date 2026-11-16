<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Launchpad\Metering\Usage;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;

/**
 * `meter:reports`, after the middleware that picks the team (`workspace` or `launchpad.api`): refuses with 429 once the team has used its quota,
 * and counts one for every call that succeeds (a response under 400). With no team chosen it does nothing.
 * shortcut: the check and the count are two steps, so calls arriving together at the quota can pass it by a few; lock per team when overage costs money.
 */
class EnforceMeter
{
    public function __construct(private readonly Usage $usage) {}

    public function handle(Request $request, Closure $next, string $meter): Response
    {
        $workspace = app(CurrentWorkspace::class)->workspace;

        if ($workspace === null) {
            return $next($request);
        }

        if (! $this->usage->within($workspace, $meter)) {
            $label = __((string) (Usage::meters()[$meter]['label'] ?? $meter));
            $limit = $this->usage->limit($workspace, $meter);
            $message = $this->usage->monthly($meter)
                ? __('This team has used all :limit of its :label for this month.', ['limit' => $limit, 'label' => $label])
                : __('This team has used all :limit of its :label.', ['limit' => $limit, 'label' => $label]);

            return $request->expectsJson() ? response()->json(['message' => $message], 429) : abort(429, $message);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            $this->usage->record($workspace, $meter);
        }

        return $response;
    }
}
