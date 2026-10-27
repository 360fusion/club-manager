<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Models\LodgeClaim;
use App\Models\LodgeCorrection;
use App\Support\Currencies;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        // The route table in the page depends on who is looking, so signing in or out forces a full page load.
        return parent::version($request).'|'.(self::routeGroup($request->user()) ?? 'all');
    }

    /**
     * Which of the Ziggy route groups in config/ziggy.php this person's pages carry; null means every route.
     */
    public static function routeGroup(?Authenticatable $user): ?string
    {
        return match (true) {
            $user === null => 'guest',
            (bool) ($user->is_super_admin ?? false) => null,
            default => 'member',
        };
    }

    /**
     * On a lodge's own domain the page is answered by its /site/{club} route; the browser keeps the address it asked for.
     */
    public function urlResolver()
    {
        return fn (Request $request) => $request->attributes->get('club_domain_uri')
            ?? Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/');
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
            // The lodge site's links drop their /site/{club} prefix when it is served on the lodge's own domain.
            'onClubDomain' => $request->attributes->has('club_domain_uri'),
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
            // The superadmin menu shows how many lodge claims and suggested fixes are waiting for a decision.
            'pendingLodgeClaims' => fn () => $user?->is_super_admin ? LodgeClaim::open()->count() : 0,
            'pendingLodgeCorrections' => fn () => $user?->is_super_admin ? LodgeCorrection::open()->count() : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'info' => fn () => $request->session()->get('info'),
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
