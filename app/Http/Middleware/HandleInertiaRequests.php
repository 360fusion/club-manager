<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => (bool) $user->is_super_admin,
                    'avatar_url' => $user->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : asset('storage/'.$user->avatar_url)) : null,
                ] : null,
                'clubs' => $user ? $user->clubs->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'role' => $c->pivot->role ?? 'member',
                    'member_number' => $c->pivot->member_number ?? '',
                    'status' => $c->pivot->status ?? 'active',
                ]) : [],
            ],
            // The bell in the header; named apart from the notifications page's own list.
            'bell' => $user ? fn () => [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()->latest()->limit(6)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'read' => $n->read_at !== null,
                    'at' => $n->created_at->toIso8601String(),
                    ...$n->data,
                ])->all(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
