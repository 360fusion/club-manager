<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateClubSettingsRequest;
use App\Models\Club;
use App\Models\MasonicHall;
use App\Services\ClubSettingsScreenData;
use App\Support\ClubAccess;
use App\Support\ClubDomain;
use App\Support\MasonicRanks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClubSettingsController extends Controller
{
    /**
     * Default Granular Permissions Matrix for Club Roles.
     */
    public static function defaultPermissionMatrix(): array
    {
        return config('club_permissions.capabilities', []);
    }

    /**
     * Display club settings dashboard with top-level tabs.
     */
    public function show(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['clubType', 'users'])
            ->firstOrFail();

        return Inertia::render('Admin/Settings/Show', app(ClubSettingsScreenData::class)->props($club));
    }

    /**
     * Update club settings and permissions matrix.
     */
    public function update(UpdateClubSettingsRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        if (array_key_exists('permission_matrix', $validated)) {
            $validated['permission_matrix'] = $this->cleanPermissionMatrix($request, $club, $validated['permission_matrix'] ?? []);
        }

        if (! empty($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);

            if ($validated['currency'] !== $club->currencyCode() && $club->currencyIsLocked() && ! $request->user()->is_super_admin) {
                throw ValidationException::withMessages(['currency' => 'This club already has financial records, so its currency can no longer be changed. Contact support if it was set by mistake.']);
            }
        }

        foreach (MasonicRanks::KINDS as $kind) {
            $key = MasonicRanks::settingKey($kind);

            if (array_key_exists($key, $validated)) {
                $validated[$key] = MasonicRanks::clean($validated[$key] ?? []);
            }
        }

        // Storage quota is a platform-level limit; only super admins may change it.
        if (array_key_exists('storage_quota_mb', $validated) && ! $request->user()->is_super_admin) {
            unset($validated['storage_quota_mb']);
        }

        if (isset($validated['name'])) {
            $club->name = $validated['name'];
        }

        if (array_key_exists('province_id', $validated)) {
            $club->province_id = $validated['province_id'];
        }

        if (array_key_exists('masonic_hall_id', $validated)) {
            $hall = $validated['masonic_hall_id'] ? MasonicHall::find($validated['masonic_hall_id']) : null;

            if ($hall && $hall->province_id && $club->province_id && (int) $hall->province_id !== (int) $club->province_id) {
                throw ValidationException::withMessages(['masonic_hall_id' => 'That masonic hall is not in the selected province.']);
            }

            $club->masonic_hall_id = $hall?->id;
            unset($validated['masonic_hall_id']);
        }

        if (isset($validated['logo_url'])) {
            $club->logo_url = $validated['logo_url'];
        }

        if (isset($validated['lodge_number'])) {
            $club->lodge_number = $validated['lodge_number'];
        }

        if (array_key_exists('custom_domain', $validated)) {
            ClubDomain::apply($club, $validated['custom_domain']);
        }

        $addressParts = array_filter([
            $validated['address_line_1'] ?? null,
            $validated['address_line_2'] ?? null,
            $validated['city'] ?? null,
            $validated['county'] ?? null,
            $validated['postcode'] ?? null,
            $validated['country'] ?? null,
        ]);
        if (! empty($addressParts)) {
            $validated['address'] = implode(', ', $addressParts);
        }

        $existingSettings = $club->settings ?? [];
        $mergedSettings = array_merge($existingSettings, $validated);

        $club->settings = $mergedSettings;
        $club->save();

        return redirect()->back()->with('success', 'Club settings updated successfully.');
    }

    /**
     * Keep only known capabilities with known roles. Only owners (and super
     * admins) may change who holds the settings capability itself, so an admin
     * cannot hand settings control to lower roles or lock others out.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function cleanPermissionMatrix(Request $request, Club $club, array $submitted): array
    {
        $current = $club->settings['permission_matrix'] ?? [];
        $current = is_array($current) ? $current : [];
        $user = $request->user();
        $isOwner = $user->is_super_admin || ClubAccess::role($user, $club) === 'owner';
        $known = array_keys(self::defaultPermissionMatrix());
        $clean = [];

        foreach ($submitted as $capability => $override) {
            if (in_array($capability, $known, true) && is_array($override) && isset($override['roles'])) {
                $clean[$capability] = ['roles' => array_values(array_unique($override['roles']))];
            }
        }

        if (! $isOwner) {
            unset($clean['manage_settings']);

            if (isset($current['manage_settings'])) {
                $clean['manage_settings'] = $current['manage_settings'];
            }
        }

        return $clean;
    }

    /**
     * Replace this lodge's rank lists with its Grand Lodge's (or the platform starter) again.
     */
    public function resetRanks(string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        MasonicRanks::resetClub($club);

        return redirect()->back()->with('success', 'Rank lists reset to the '.($club->province?->grandLodge?->name ?? 'standard').' list.');
    }
}
