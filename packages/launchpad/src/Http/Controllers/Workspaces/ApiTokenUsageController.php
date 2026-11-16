<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\ApiToken;
use Launchpad\Models\ApiTokenUsage;
use Launchpad\Workspaces\CurrentWorkspace;

/** One API token of the workspace: who made it, who called last, and what it did in the last 30 days. For whoever manages the workspace. */
class ApiTokenUsageController extends Controller
{
    private const DAYS = 30;

    public function show(string $slug, CurrentWorkspace $current, string $token): View|Responsable
    {
        $workspace = $current->workspace;
        $token = ApiToken::where('workspace_id', $workspace->id)->whereKey($token)->with('tokenable')->firstOrFail();

        $used = ApiTokenUsage::where('token_id', $token->id)->where('day', '>=', now()->utc()->subDays(self::DAYS - 1)->toDateString())->get()
            ->keyBy(fn (ApiTokenUsage $u) => $u->day->toDateString());

        // every day, newest first, so a quiet day shows as a 0 rather than a gap
        $days = collect(range(0, self::DAYS - 1))->map(function (int $ago) use ($used) {
            $date = now()->utc()->subDays($ago)->toDateString();

            return ['date' => $date, 'requests' => $used[$date]->requests ?? 0, 'errors' => $used[$date]->errors ?? 0];
        })->all();
        $graces = collect(ApiTokenController::GRACES)->map(fn ($g, $value) => ['value' => $value, 'label' => ApiTokenController::graceLabel($value)])->values()->all();
        $totals = ['requests' => array_sum(array_column($days, 'requests')), 'errors' => array_sum(array_column($days, 'errors'))];

        return Ui::render('workspaces.api-token', ['workspace' => $workspace, 'token' => $token, 'days' => $days, 'totals' => $totals, 'graces' => $graces], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'token' => [
                'id' => $token->id, 'name' => $token->name, 'abilities' => $token->abilities, 'createdBy' => $token->tokenable?->name, 'createdAt' => $token->created_at,
                'lastUsedAt' => $token->last_used_at, 'lastIp' => $token->last_ip, 'lastUserAgent' => $token->last_user_agent,
                'expiresAt' => $token->expires_at, 'expired' => $token->expires_at?->isPast() ?? false, 'replaced' => $token->replaced_at !== null,
            ],
            'days' => $days,
            'totals' => $totals,
            'graces' => $graces,
        ]);
    }
}
