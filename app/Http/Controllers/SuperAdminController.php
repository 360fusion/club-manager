<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\DefaultEmailTemplate;
use App\Models\District;
use App\Models\GrandLodge;
use App\Models\Meeting;
use App\Models\Province;
use App\Models\User;
use App\Support\Currencies;
use App\Support\OrderColours;
use Database\Seeders\ClubTypeSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
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

    /**
     * List and manage Club Types, Officer Ladders, Ranks, and Terminology.
     */
    public function clubTypesIndex(): Response
    {
        $clubTypes = ClubType::with(['defaultOfficerRoles', 'defaultRanks'])->get();

        return Inertia::render('SuperAdmin/ClubTypes/Index', [
            'clubTypes' => $clubTypes,
        ]);
    }

    /**
     * Display a dedicated details page for a specific Masonic Order / Club Type.
     */
    public function showClubType(int $id): Response
    {
        $clubType = ClubType::with(['defaultOfficerRoles', 'defaultRanks'])
            ->withCount('clubs')
            ->findOrFail($id);

        $clubs = Club::where('club_type_id', $id)
            ->with(['province'])
            ->withCount('users')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'lodge_number' => $c->lodge_number ?? '—',
                'province' => $c->province?->name ?? '—',
                'members_count' => $c->users_count,
                'created_at' => $c->created_at ? $c->created_at->format('d M Y') : '—',
            ]);

        $allClubTypes = ClubType::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('SuperAdmin/ClubTypes/Show', [
            'clubType' => $clubType,
            'clubs' => $clubs,
            'allClubTypes' => $allClubTypes,
        ]);
    }

    /**
     * Store a new Club Type preset.
     */
    public function storeClubType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:club_types,code',
            'colour' => ['nullable', Rule::in(OrderColours::KEYS)],
            'description' => 'nullable|string|max:10000',
            'website_url' => 'nullable|url|max:255',
            'available_modules' => 'nullable|array|max:50',
            'terminology' => 'nullable|array|max:100',
            'rulers_schema' => 'nullable|array|max:100',
        ]);

        $ct = ClubType::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'colour' => $validated['colour'] ?? OrderColours::for($validated['code']),
            'description' => $validated['description'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'available_modules' => $validated['available_modules'] ?? ['accounting', 'meetings', 'members', 'charity'],
            'default_settings' => [],
            'terminology' => $validated['terminology'] ?? [],
            'rulers_schema' => $validated['rulers_schema'] ?? [],
        ]);

        return redirect()->back()->with('success', "Club Type '{$ct->name}' created.");
    }

    /**
     * Update an existing Club Type preset.
     */
    public function updateClubType(Request $request, int $id): RedirectResponse
    {
        $clubType = ClubType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'colour' => ['nullable', Rule::in(OrderColours::KEYS)],
            'description' => 'nullable|string|max:10000',
            'website_url' => 'nullable|url|max:255',
            'available_modules' => 'nullable|array|max:50',
            'terminology' => 'nullable|array|max:100',
            'rulers_schema' => 'nullable|array|max:100',
        ]);

        $clubType->update([
            'name' => $validated['name'],
            'colour' => $validated['colour'] ?? $clubType->colour,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $clubType->description,
            'website_url' => array_key_exists('website_url', $validated) ? $validated['website_url'] : $clubType->website_url,
            'available_modules' => $validated['available_modules'] ?? $clubType->available_modules,
            'terminology' => $validated['terminology'] ?? $clubType->terminology,
            'rulers_schema' => $validated['rulers_schema'] ?? $clubType->rulers_schema,
        ]);

        return redirect()->back()->with('success', "Club Type '{$clubType->name}' updated.");
    }

    /**
     * List default email templates.
     */
    public function emailTemplatesIndex(): Response
    {
        $templates = DefaultEmailTemplate::all();

        if ($templates->isEmpty()) {
            (new ClubTypeSeeder)->run();
            $templates = DefaultEmailTemplate::all();
        }

        return Inertia::render('SuperAdmin/EmailTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Update a default email template.
     */
    public function updateEmailTemplate(Request $request, int $id): RedirectResponse
    {
        $template = DefaultEmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string|max:200000',
        ]);

        $template->update($validated);

        return redirect()->back()->with('success', "Email template '{$template->name}' updated.");
    }

    /**
     * Email the template, with each placeholder shown as its own name, to the signed-in superadmin so they can see how it reads.
     */
    public function sendTestEmailTemplate(Request $request, int $id): RedirectResponse
    {
        $template = DefaultEmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string|max:200000',
        ]);

        $samples = collect($template->available_placeholders ?? [])->mapWithKeys(fn (string $name) => ['{{'.$name.'}}' => '['.$name.']'])->all();
        $subject = trim(str_replace(["\r", "\n"], ' ', strtr($validated['subject'], $samples)));
        $body = strtr($validated['body_html'], $samples);

        Mail::html($body, fn ($message) => $message->to($request->user()->email)->subject('[Test] '.$subject));

        return redirect()->back()->with('success', 'A test of this template has been sent to '.$request->user()->email.'.');
    }

    /**
     * List and manage Grand Lodges / Governing Bodies.
     */
    public function grandLodgesIndex(): Response
    {
        $grandLodges = GrandLodge::withCount(['provinces', 'clubs'])->get();

        return Inertia::render('SuperAdmin/GrandLodges/Index', [
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * Store a new Grand Lodge.
     */
    public function storeGrandLodge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:grand_lodges,code',
            'short_name' => 'nullable|string|max:50',
            'country' => 'required|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',
        ]);

        $grandLodge = GrandLodge::create($validated);

        return redirect()->back()->with('success', "Grand Lodge '{$grandLodge->name}' created successfully.");
    }

    /**
     * Update a Grand Lodge.
     */
    public function updateGrandLodge(Request $request, int $id): RedirectResponse
    {
        $grandLodge = GrandLodge::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'short_name' => 'nullable|string|max:50',
            'country' => 'required|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',
        ]);

        $grandLodge->update($validated);

        return redirect()->back()->with('success', "Grand Lodge '{$grandLodge->name}' updated successfully.");
    }

    /**
     * Delete a Grand Lodge.
     */
    public function destroyGrandLodge(int $id): RedirectResponse
    {
        $grandLodge = GrandLodge::findOrFail($id);
        $name = $grandLodge->name;
        $grandLodge->delete();

        return redirect()->back()->with('success', "Grand Lodge '{$name}' deleted.");
    }

    /**
     * List and manage Masonic Provinces.
     */
    public function provincesIndex(): Response
    {
        $provinces = Province::with(['grandLodge'])->withCount('clubs')->orderBy('name')->get();
        $grandLodges = GrandLodge::all();

        return Inertia::render('SuperAdmin/Provinces/Index', [
            'provinces' => $provinces,
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * View dedicated specification page for a Masonic Province.
     */
    public function showProvince(int $id): Response
    {
        $province = Province::with(['grandLodge', 'clubs.clubType'])
            ->withCount('clubs')
            ->findOrFail($id);

        $allProvinces = Province::select('id', 'name', 'code', 'country')->orderBy('name')->get();
        $grandLodges = GrandLodge::all();

        return Inertia::render('SuperAdmin/Provinces/Show', [
            'province' => $province,
            'allProvinces' => $allProvinces,
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * Dedicated edit form page for a Masonic Province.
     */
    public function editProvince(int $id): Response
    {
        $province = Province::with(['grandLodge'])->findOrFail($id);
        $grandLodges = GrandLodge::all();

        return Inertia::render('SuperAdmin/Provinces/Edit', [
            'province' => $province,
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * Store a new Masonic Province.
     */
    public function storeProvince(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'grand_lodge_id' => 'nullable|exists:grand_lodges,id',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:provinces,code',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'provincial_grand_master' => 'nullable|string|max:150',
            'provincial_grand_secretary' => 'nullable|string|max:150',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'town' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'twitter_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',
        ]);

        $province = Province::create($validated);

        return redirect()->route('superadmin.provinces.show', $province->id)->with('success', "Province '{$province->name}' created successfully.");
    }

    /**
     * Update a Masonic Province.
     */
    public function updateProvince(Request $request, int $id): RedirectResponse
    {
        $province = Province::findOrFail($id);

        $validated = $request->validate([
            'grand_lodge_id' => 'nullable|exists:grand_lodges,id',
            'name' => 'required|string|max:150',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'provincial_grand_master' => 'nullable|string|max:150',
            'provincial_grand_secretary' => 'nullable|string|max:150',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'town' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'twitter_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',
        ]);

        $province->update($validated);

        return redirect()->route('superadmin.provinces.show', $province->id)->with('success', "Province '{$province->name}' updated successfully.");
    }

    /**
     * Delete a Masonic Province.
     */
    public function destroyProvince(int $id): RedirectResponse
    {
        $province = Province::findOrFail($id);
        $name = $province->name;
        $province->delete();

        return redirect()->back()->with('success', "Province '{$name}' deleted.");
    }

    /**
     * Developer utility helper route to promote the authenticated user to superadmin.
     */
    public function makeMeSuperAdmin(Request $request): RedirectResponse
    {
        // Local-only bootstrap helper. Exposing this anywhere else lets any
        // authenticated user grant themselves full platform access.
        // Use `php artisan superadmin:grant {email}` on deployed environments.
        abort_unless(app()->isLocal(), 404);

        $user = $request->user();
        if ($user) {
            $user->forceFill(['is_super_admin' => true])->save();

            return redirect()->route('superadmin.dashboard')->with('success', 'You are now a Superadmin!');
        }

        return redirect()->route('login');
    }

    // ─── Districts & Groups ───────────────────────────────────────────────────

    /**
     * List all UGLE Districts and Groups (overseas bodies).
     */
    public function districtsIndex(): Response
    {
        $districts = District::with(['grandLodge'])->orderBy('type')->orderBy('name')->get();
        $grandLodges = GrandLodge::all();

        return Inertia::render('SuperAdmin/Districts/Index', [
            'districts' => $districts,
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * View a District / Group detail page.
     */
    public function showDistrict(int $id): Response
    {
        $district = District::with(['grandLodge'])->findOrFail($id);
        $grandLodges = GrandLodge::all();

        return Inertia::render('SuperAdmin/Districts/Show', [
            'district' => $district,
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * Edit form for a District / Group.
     */
    public function editDistrict(int $id): Response
    {
        $district = District::with(['grandLodge'])->findOrFail($id);
        $grandLodges = GrandLodge::all();

        return Inertia::render('SuperAdmin/Districts/Edit', [
            'district' => $district,
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * Store a new District / Group.
     */
    public function storeDistrict(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'grand_lodge_id' => 'nullable|exists:grand_lodges,id',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:districts,code',
            'type' => 'required|in:district,group,dormant',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'district_grand_master' => 'nullable|string|max:150',
            'district_grand_secretary' => 'nullable|string|max:150',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'town' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'twitter_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',
        ]);

        $district = District::create($validated);

        return redirect()->route('superadmin.districts.show', $district->id)->with('success', "'{$district->name}' created successfully.");
    }

    /**
     * Update a District / Group.
     */
    public function updateDistrict(Request $request, int $id): RedirectResponse
    {
        $district = District::findOrFail($id);

        $validated = $request->validate([
            'grand_lodge_id' => 'nullable|exists:grand_lodges,id',
            'name' => 'required|string|max:150',
            'type' => 'required|in:district,group,dormant',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'district_grand_master' => 'nullable|string|max:150',
            'district_grand_secretary' => 'nullable|string|max:150',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'town' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'twitter_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',
        ]);

        $district->update($validated);

        return redirect()->route('superadmin.districts.show', $district->id)->with('success', "'{$district->name}' updated successfully.");
    }

    /**
     * Delete a District / Group.
     */
    public function destroyDistrict(int $id): RedirectResponse
    {
        $district = District::findOrFail($id);
        $name = $district->name;
        $district->delete();

        return redirect()->route('superadmin.districts.index')->with('success', "'{$name}' deleted.");
    }

    /**
     * Australian Grand Lodges reference page.
     */
    public function australianGrandLodges(): Response
    {
        $australianGrandLodges = GrandLodge::withCount(['provinces', 'clubs'])
            ->where('country', 'Australia')
            ->orderBy('name')
            ->get();

        return inertia('SuperAdmin/GrandLodges/Australia', [
            'australianGrandLodges' => $australianGrandLodges,
        ]);
    }

    /**
     * United States Grand Lodges reference page.
     */
    public function usGrandLodges(): Response
    {
        $usGrandLodges = GrandLodge::withCount(['provinces', 'clubs'])
            ->where('country', 'United States')
            ->orderBy('name')
            ->get();

        return inertia('SuperAdmin/GrandLodges/UnitedStates', [
            'usGrandLodges' => $usGrandLodges,
        ]);
    }

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
