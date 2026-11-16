<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Mcp\McpAccess;
use Launchpad\Models\ApiToken;
use Launchpad\Workspaces\CurrentWorkspace;

/** The API page of a workspace: tokens for its API (routes/api.php), made and revoked by whoever holds manage-workspace */
class ApiTokenController extends Controller
{
    /** What the expiry choice on the form means, in days (null: never) */
    private const EXPIRY = ['never' => null, '30' => 30, '90' => 90, '365' => 365];

    /** What the choice of how long the old token keeps working means, in minutes, with its words */
    public const GRACES = ['now' => [0, 'Not at all: stop it now'], '1h' => [60, '1 hour'], '24h' => [1440, '24 hours'], '7d' => [10080, '7 days']];

    /** GRACES' words, translated: literal keys here so the scanner finds them */
    public static function graceLabel(string $key): string
    {
        return match ($key) {
            'now' => __('Not at all: stop it now'),
            '1h' => __('1 hour'),
            '24h' => __('24 hours'),
            '7d' => __('7 days'),
            default => $key,
        };
    }

    public function index(CurrentWorkspace $current): View|Responsable
    {
        $workspace = $current->workspace;
        $tokens = ApiToken::where('workspace_id', $workspace->id)->orderByDesc('id')->get();
        $abilities = (array) config('launchpad.api.abilities');
        $newToken = session('new_token');

        $mcpUrl = Route::has('launchpad.mcp') && McpAccess::allowedFor($workspace) ? route('launchpad.mcp') : null;

        return Ui::render('workspaces.api', [
            'workspace' => $workspace, 'tokens' => $tokens, 'abilities' => $abilities, 'newToken' => $newToken, 'mcpUrl' => $mcpUrl,
        ], fn () => [
            'mcpUrl' => $mcpUrl,
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'tokens' => $tokens->map(fn (ApiToken $t) => [
                'id' => $t->id, 'name' => $t->name, 'abilities' => $t->abilities, 'last_used_at' => $t->last_used_at, 'last_ip' => $t->last_ip, 'expires_at' => $t->expires_at,
                'expired' => $t->expires_at?->isPast() ?? false, 'replaced' => $t->replaced_at !== null,
            ])->values(),
            'abilities' => collect($abilities)->map(fn ($label, $key) => ['key' => $key, 'label' => __($label)])->values(),
            'expiries' => [['value' => 'never', 'label' => __('Never')], ['value' => '30', 'label' => __('In 30 days')], ['value' => '90', 'label' => __('In 90 days')], ['value' => '365', 'label' => __('In 1 year')]],
            'newToken' => $newToken,
        ]);
    }

    public function store(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'expiry' => ['required', Rule::in(array_keys(self::EXPIRY))],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_keys((array) config('launchpad.api.abilities')))],
        ]);

        $days = self::EXPIRY[$data['expiry']];
        [, $plain] = ApiToken::issue($current->workspace, trim($data['name']), $data['abilities'], $request->user(), $days ? now()->addDays($days) : null);

        // flashed, so it is on the next page once and nowhere after
        return redirect()->route('workspace.api', $current->workspace->slug)->with('new_token', $plain)->with('status', __('Token created. Copy it now: it will not be shown again.'));
    }

    public function rotate(Request $request, string $slug, CurrentWorkspace $current, string $token): RedirectResponse
    {
        $data = $request->validate(['grace' => ['required', Rule::in(array_keys(self::GRACES))]]);
        $old = ApiToken::where('workspace_id', $current->workspace->id)->whereKey($token)->firstOrFail();

        [, $plain] = ApiToken::rotate($old, self::GRACES[$data['grace']][0]);

        return redirect()->route('workspace.api', $slug)->with('new_token', $plain)->with('status', __('Token replaced. Copy the new one now: it will not be shown again.'));
    }

    public function destroy(string $slug, CurrentWorkspace $current, string $token): RedirectResponse
    {
        ApiToken::where('workspace_id', $current->workspace->id)->whereKey($token)->firstOrFail()->delete();

        return back()->with('status', __('Token revoked.'));
    }
}
