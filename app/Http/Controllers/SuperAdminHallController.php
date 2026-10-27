<?php

namespace App\Http\Controllers;

use App\Models\MasonicHall;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Masonic halls. Split out of SuperAdminController; the URLs, route names and permissions are unchanged.
 */
class SuperAdminHallController extends Controller
{
    /**
     * List every masonic hall, with the number of lodges and other bodies that meet in it.
     */
    public function masonicHallsIndex(): Response
    {
        return Inertia::render('SuperAdmin/MasonicHalls/Index', [
            'halls' => MasonicHall::with('province:id,name')->withCount('clubs')->orderBy('name')->get(),
            'provinces' => Province::orderBy('name')->get(['id', 'name']),
            'kinds' => MasonicHall::KINDS,
        ]);
    }

    /**
     * Add a masonic hall.
     */
    public function storeMasonicHall(Request $request): RedirectResponse
    {
        $validated = $this->validateMasonicHall($request);
        $validated['slug'] = $this->uniqueMasonicHallSlug($validated['name']);

        $hall = MasonicHall::create($validated);

        return redirect()->route('superadmin.masonic_halls.index')->with('success', "'{$hall->name}' added.");
    }

    /**
     * Update a masonic hall.
     */
    public function updateMasonicHall(Request $request, int $id): RedirectResponse
    {
        $hall = MasonicHall::findOrFail($id);
        $hall->update($this->validateMasonicHall($request));

        return redirect()->route('superadmin.masonic_halls.index')->with('success', "'{$hall->name}' updated.");
    }

    /**
     * Delete a masonic hall. Lodges that met there are simply left without a hall.
     */
    public function destroyMasonicHall(int $id): RedirectResponse
    {
        $hall = MasonicHall::findOrFail($id);
        $name = $hall->name;
        $hall->delete();

        return redirect()->route('superadmin.masonic_halls.index')->with('success', "'{$name}' deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMasonicHall(Request $request): array
    {
        return $request->validate([
            'province_id' => 'nullable|exists:provinces,id',
            'name' => 'required|string|max:255',
            'kind' => ['sometimes', Rule::in(array_keys(MasonicHall::KINDS))],
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'town' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website_url' => ['nullable', 'url', 'max:255', 'regex:#^https?://#i'],
        ]);
    }

    private function uniqueMasonicHallSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'masonic-hall';
        $slug = $base;

        for ($i = 2; MasonicHall::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
