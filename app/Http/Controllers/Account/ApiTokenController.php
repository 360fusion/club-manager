<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A person's own API tokens: see them, make one, revoke one. Tokens made at /api/v1/tokens/create by an app show up here too. */
class ApiTokenController extends Controller
{
    private const EXPIRY_DAYS = [30, 90, 365];

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/ApiTokens', [
            'tokens' => $user->tokens()->latest()->get()->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used' => $t->last_used_at?->diffForHumans(),
                'expires' => $t->expires_at?->format('j M Y'),
                'created' => $t->created_at->format('j M Y'),
            ]),
            'twoFactorOn' => filled($user->two_factor_confirmed_at),
            'expiryDays' => self::EXPIRY_DAYS,
            'newToken' => session('new_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // The same rule as /api/v1/tokens/create: a token would skip the second step.
        if (filled($user->two_factor_confirmed_at)) {
            return back()->withErrors(['name' => 'Tokens cannot be made for an account that uses two-factor sign-in, because a token would skip the second step.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'days' => ['required', 'integer', 'in:'.implode(',', self::EXPIRY_DAYS)],
        ]);

        $token = $user->createToken($data['name'], ['profile:read'], now()->addDays((int) $data['days']));

        // Shown once: only a hash is kept.
        return back()->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return back()->with('success', 'Token revoked.');
    }
}
