<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuperAdmin\StoreDistrictRequest;
use App\Http\Requests\SuperAdmin\StoreProvinceRequest;
use App\Http\Requests\SuperAdminProvince\UpdateDistrictRequest;
use App\Http\Requests\SuperAdminProvince\UpdateProvinceRequest;
use App\Models\District;
use App\Models\GrandLodge;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Provinces, and the districts and groups overseas. Split out of SuperAdminController; the URLs, route names and permissions are unchanged.
 */
class SuperAdminProvinceController extends Controller
{
    /**
     * List and manage Masonic Provinces.
     */
    public function provincesIndex(): Response
    {
        $provinces = Province::with('grandLodge:id,name,short_name')->withCount('clubs')->orderBy('name')->get();
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
            'admins' => $province->admins()->orderBy('name')->get(['users.id', 'users.name', 'users.email']),
            'allProvinces' => $allProvinces,
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * Make someone with an account an admin of the province: they review suggested fixes to its lodges' listings.
     */
    public function addAdmin(Request $request, int $id): RedirectResponse
    {
        $province = Province::findOrFail($id);
        $validated = $request->validate(['email' => 'required|email|max:255']);
        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No one has an account with that email address yet.']);
        }

        $province->admins()->syncWithoutDetaching([$user->id]);

        return back()->with('success', "{$user->name} now looks after the {$province->name} lodge listings.");
    }

    public function removeAdmin(int $id, int $userId): RedirectResponse
    {
        Province::findOrFail($id)->admins()->detach($userId);

        return back()->with('success', 'Removed as an admin of this province.');
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
    public function storeProvince(StoreProvinceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $province = Province::create($validated);

        return redirect()->route('superadmin.provinces.show', $province->id)->with('success', "Province '{$province->name}' created successfully.");
    }

    /**
     * Update a Masonic Province.
     */
    public function updateProvince(UpdateProvinceRequest $request, int $id): RedirectResponse
    {
        $province = Province::findOrFail($id);

        $validated = $request->validated();

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
    public function storeDistrict(StoreDistrictRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $district = District::create($validated);

        return redirect()->route('superadmin.districts.show', $district->id)->with('success', "'{$district->name}' created successfully.");
    }

    /**
     * Update a District / Group.
     */
    public function updateDistrict(UpdateDistrictRequest $request, int $id): RedirectResponse
    {
        $district = District::findOrFail($id);

        $validated = $request->validated();

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
}
