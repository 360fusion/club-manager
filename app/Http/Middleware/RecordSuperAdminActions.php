<?php

namespace App\Http\Middleware;

use App\Models\SuperAdminAction;
use App\Support\SuperAdminLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leaves a trace of every superadmin request that changes something: who, which route, what was submitted, and for each
 * record saved, created or deleted along the way its before and after. Only runs inside the superadmin area, so the
 * platform's own housekeeping (queues, sessions, the app's other logs) never fills it.
 */
class RecordSuperAdminActions
{
    /** Tables the request writes to as a side effect that say nothing about what the superadmin did */
    private const IGNORED = [SuperAdminAction::class];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $changes = [];
        $record = function (string $event, Model $model) use (&$changes) {
            if (in_array($model::class, self::IGNORED, true)) {
                return;
            }

            $before = $event === 'updated' ? array_intersect_key($model->getOriginal(), $model->getChanges()) : ($event === 'deleted' ? $model->getAttributes() : []);
            $after = $event === 'deleted' ? [] : ($event === 'updated' ? $model->getChanges() : $model->getAttributes());

            $changes[] = [
                'event' => $event,
                'model' => class_basename($model),
                'id' => $model->getKey(),
                'before' => SuperAdminLog::clean($before),
                'after' => SuperAdminLog::clean($after),
            ];
        };

        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", fn (string $name, array $payload) => $record($event, $payload[0]));
        }

        $response = $next($request);

        SuperAdminAction::create([
            'actor_id' => $request->user()?->id,
            'action' => (string) ($request->route()?->getName() ?? $request->path()),
            'method' => $request->method(),
            'path' => mb_substr($request->path(), 0, 500),
            'status' => $response->getStatusCode(),
            'route_parameters' => SuperAdminLog::clean(array_map(fn ($v) => $v instanceof Model ? $v->getKey() : $v, $request->route()?->parameters() ?? [])) ?: null,
            'input' => SuperAdminLog::clean($request->except(['_token', '_method'])) ?: null,
            'changes' => $changes ?: null,
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
