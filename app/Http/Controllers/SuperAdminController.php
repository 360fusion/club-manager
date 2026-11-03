<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Currencies;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    /**
     * System Analytics Dashboard for Superadmins.
     */
    public function dashboard(): Response
    {
        $totalClubsCount = Club::count();
        $totalUsersCount = User::count();
        $totalMeetingsCount = Meeting::count();
        $totalGrantsDisbursed = $this->grantsDisbursedByCurrency();

        $clubsByType = ClubType::with(['defaultOfficerRoles'])->withCount('clubs')->get()->map(fn ($ct) => [
            'id' => $ct->id,
            'name' => $ct->name,
            'code' => $ct->code,
            'description' => $ct->description,
            'website_url' => $ct->website_url,
            'terminology' => $ct->terminology,
            'rulers_schema' => $ct->rulers_schema,
            'default_officer_roles' => $ct->defaultOfficerRoles,
            'count' => $ct->clubs_count,
        ]);

        $clubs = Club::with(['clubType', 'province'])
            ->withCount('users')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'lodge_number' => $c->lodge_number ?? '—',
                'club_type' => $c->clubType?->name ?? 'General',
                'province' => $c->province?->name ?? '—',
                'members_count' => $c->users_count,
                'created_at' => $c->created_at ? $c->created_at->format('d M Y') : '—',
            ]);

        return Inertia::render('SuperAdmin/Dashboard', [
            'metrics' => [
                'totalClubsCount' => $totalClubsCount,
                'totalUsersCount' => $totalUsersCount,
                'totalMeetingsCount' => $totalMeetingsCount,
                'totalGrantsDisbursed' => $totalGrantsDisbursed,
            ],
            'clubsByType' => $clubsByType,
            'clubs' => $clubs,
        ]);
    }

    // ─── Districts & Groups ───────────────────────────────────────────────────

    // ─── Masonic Halls ────────────────────────────────────────────────────────

    /**
     * Grants total per currency (lodges keep books in different currencies, so a single sum would be meaningless).
     */
    private function grantsDisbursedByCurrency(): string
    {
        $totals = [];

        Club::with('province.grandLodge')->get()->each(function (Club $club) use (&$totals) {
            $sum = (float) CharityGrant::where('club_id', $club->id)->sum('amount');

            if ($sum > 0) {
                $totals[$club->currencyCode()] = ($totals[$club->currencyCode()] ?? 0) + $sum;
            }
        });

        if ($totals === []) {
            return Currencies::symbol(Currencies::DEFAULT).'0.00';
        }

        return collect($totals)->map(fn ($sum, $code) => Currencies::symbol($code).number_format($sum, 2))->implode(' · ');
    }
}
