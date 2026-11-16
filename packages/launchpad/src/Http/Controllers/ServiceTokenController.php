<?php

namespace Launchpad\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Launchpad\Models\Membership;
use Launchpad\Tokens\ServiceToken;

class ServiceTokenController extends Controller
{
    /** The token for the workspace the person asks for (?workspace=slug) or else the one they are working in. Never cached. */
    public function __invoke(Request $request, ServiceToken $tokens): JsonResponse
    {
        abort_unless($tokens->enabled(), 503, 'The service token is not set up.');

        $user = $request->user();
        $membership = Membership::where('user_id', $user->getKey())
            ->when($request->query('workspace'), fn ($query, $slug) => $query->whereHas('workspace', fn ($q) => $q->where('slug', $slug)))
            ->when(! $request->query('workspace') && $user->current_workspace_id, fn ($query) => $query->where('workspace_id', $user->current_workspace_id))
            ->with('workspace')->first();

        abort_if($membership === null, 404);

        return response()->json(['token' => $tokens->issue($user, $membership->workspace), 'expires_in' => $tokens->ttl()], 200, ['Cache-Control' => 'no-store, private']);
    }
}
