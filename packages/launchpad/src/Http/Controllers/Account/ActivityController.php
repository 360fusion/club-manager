<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\AccountEvent;
use Launchpad\Support\UserAgent;

/** The Activity page: a person's own recent sign-ins and security changes */
class ActivityController extends Controller
{
    public function show(Request $request): View|Responsable
    {
        $events = AccountEvent::where('user_id', $request->user()->getKey())->latest('id')->limit(50)->get()->map(fn ($e) => [
            'label' => AccountEvent::label($e->type),
            'device' => UserAgent::describe($e->user_agent),
            'ip' => $e->ip,
            'at' => $e->created_at,
        ]);

        return Ui::render('account.activity', ['events' => $events], fn () => ['events' => $events->map(fn ($e) => ['label' => $e['label'], 'device' => $e['device'], 'ip' => $e['ip'], 'at' => $e['at']?->toIso8601String()])]);
    }
}
