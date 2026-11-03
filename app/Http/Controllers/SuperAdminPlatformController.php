<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use App\Support\AccountClosure;
use App\Support\BillingChecks;
use App\Support\HealthChecks;
use App\Support\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only lists for running the platform: who has an account, which clubs pay, what Stripe sent, what admins did, and is it set up */
class SuperAdminPlatformController extends Controller
{
    public function users(Request $request): Response
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()->withCount('clubs')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%")))
            ->orderBy('id', 'desc')->paginate(25)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'verified' => $u->email_verified_at ? 'Yes' : 'No',
                'two_factor' => $u->two_factor_confirmed_at ? 'On' : 'Off',
                'clubs' => $u->clubs_count,
                'status' => match (true) {
                    (bool) $u->purged_at => 'Closed',
                    (bool) $u->closure_requested_at => 'Closing '.AccountClosure::purgeDate($u)->format('j M Y'),
                    (bool) $u->is_super_admin => 'Platform admin',
                    default => 'Active',
                },
                'joined' => $u->created_at?->format('j M Y'),
            ]);

        return $this->table('Users', 'Everyone with an account. Search by name or email.', [
            ['key' => 'name', 'label' => 'Name'], ['key' => 'email', 'label' => 'Email'], ['key' => 'verified', 'label' => 'Email verified'],
            ['key' => 'two_factor', 'label' => '2FA'], ['key' => 'clubs', 'label' => 'Clubs'], ['key' => 'status', 'label' => 'Status'], ['key' => 'joined', 'label' => 'Joined'],
        ], $users, search: true, q: $search);
    }

    public function clubs(Request $request): Response
    {
        $search = trim((string) $request->query('q'));

        $clubs = Club::query()->withCount(['users' => fn ($q) => $q->where('club_user.status', 'active')])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('slug', "%{$search}%")))
            ->orderBy('id', 'desc')->paginate(25)->withQueryString()
            ->through(function (Club $c) {
                $subscription = $c->subscription('default');

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'members' => $c->users_count,
                    'plan' => PlanCatalog::forClub($c)['name'],
                    'subscription' => $subscription->stripe_status ?? 'none',
                    'stripe_customer' => $c->stripe_id ?? '',
                    'created' => $c->created_at?->format('j M Y'),
                ];
            });

        return $this->table('Clubs', 'Every club, its plan and its Stripe subscription. Search by name or address.', [
            ['key' => 'name', 'label' => 'Club'], ['key' => 'slug', 'label' => 'Address'], ['key' => 'members', 'label' => 'Active people'],
            ['key' => 'plan', 'label' => 'Plan'], ['key' => 'subscription', 'label' => 'Stripe status'], ['key' => 'stripe_customer', 'label' => 'Stripe customer'], ['key' => 'created', 'label' => 'Created'],
        ], $clubs, search: true, q: $search);
    }

    public function stripeEvents(): Response
    {
        $events = DB::table('stripe_events')->orderByDesc('id')->paginate(50)->through(fn ($e) => [
            'id' => $e->id, 'stripe_id' => $e->stripe_id, 'type' => $e->type, 'customer' => $e->customer_id ?? '', 'received' => Carbon::parse($e->received_at)->format('j M Y H:i'),
        ]);

        return $this->table('Stripe events', 'What Stripe has told the platform about club subscriptions. Each event id appears once, however often Stripe sent it.', [
            ['key' => 'received', 'label' => 'Received'], ['key' => 'type', 'label' => 'Type'], ['key' => 'customer', 'label' => 'Customer'], ['key' => 'stripe_id', 'label' => 'Event id'],
        ], $events);
    }

    public function health(): Response
    {
        $checks = collect([...HealthChecks::run(), ...BillingChecks::run()])->map(fn ($c, $i) => ['id' => $i, 'name' => $c['name'], 'ok' => $c['ok'], 'detail' => $c['detail']]);

        return $this->table('System health', 'Is the platform set up to be live and to take payments? The same checks as php artisan app:doctor and php artisan billing:doctor.', [
            ['key' => 'name', 'label' => 'Check'], ['key' => 'ok', 'label' => 'OK', 'badge' => true], ['key' => 'detail', 'label' => 'Detail'],
        ], $checks);
    }

    /** @param  list<array<string, mixed>>  $columns */
    private function table(string $title, string $description, array $columns, mixed $rows, bool $search = false, string $q = ''): Response
    {
        $paginated = $rows instanceof LengthAwarePaginator;

        return Inertia::render('SuperAdmin/Platform/Table', [
            'title' => $title,
            'description' => $description,
            'columns' => $columns,
            'rows' => $paginated ? $rows->items() : $rows->values()->all(),
            'links' => $paginated ? $rows->linkCollection()->toArray() : [],
            'search' => $search,
            'q' => $q,
        ]);
    }
}
