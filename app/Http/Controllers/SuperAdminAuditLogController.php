<?php

namespace App\Http\Controllers;

use App\Models\SuperAdminAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The read-only list of what superadmins have done, newest first.
 */
class SuperAdminAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $actions = SuperAdminAction::with('actor:id,name,email')
            ->when($request->filled('actor'), fn ($q) => $q->where('actor_id', (int) $request->input('actor')))
            ->when($request->filled('search'), fn ($q) => $q->where('action', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('search')).'%'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (SuperAdminAction $a) => [
                'id' => $a->id,
                'when' => $a->created_at->format('j M Y H:i:s'),
                'actor' => data_get($a, 'actor.name', 'Deleted user'),
                'actor_email' => data_get($a, 'actor.email'),
                'action' => $a->action,
                'method' => $a->method,
                'path' => $a->path,
                'status' => $a->status,
                'input' => $a->input,
                'changes' => $a->changes,
                'ip' => $a->ip,
            ]);

        return Inertia::render('SuperAdmin/AuditLog/Index', [
            'actions' => $actions,
            'filters' => $request->only(['actor', 'search']),
        ]);
    }
}
