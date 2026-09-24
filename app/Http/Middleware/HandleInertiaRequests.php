<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Models\LodgeClaim;
use App\Support\Currencies;
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
            'currency' => fn () => $this->currencyFor($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => (bool) $user->is_super_admin,
                    'avatar_url' => $user->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : asset('storage/'.$user->avatar_url)) : null,
                ] : null,
                'clubs' => $user ? $user->clubs()->with('clubType')->get()->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'colour' => $c->colourKey(),
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
            // The superadmin menu shows how many lodge claims are waiting for a decision.
            'pendingLodgeClaims' => fn () => $user?->is_super_admin ? LodgeClaim::open()->count() : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * The currency of the club named in the URL, so every page shows amounts in it.
     * Pages that span several clubs get the default; they format amounts per club on the server.
     *
     * @return array{code: string, name: string, symbol: string}
     */
    private function currencyFor(Request $request): array
    {
        $slug = $request->route('clubSlug') ?? $request->route('slug');

        $club = is_string($slug) && $slug !== ''
            ? Club::with('province.grandLodge')->where('slug', $slug)->first()
            : null;

        return Currencies::describe($club?->currencyCode() ?? Currencies::DEFAULT);
    }
}
