<?php

namespace App\Services;

use App\Models\Club;

/**
 * The searchable national directory of lodges and clubs.
 */
class ClubDirectoryService
{
    /**
     * @return array{clubs: list<array<string, mixed>>, regions: list<string>, filters: array{search: string, region: string}}
     */
    public function listing(string $search = '', string $region = ''): array
    {
        $query = Club::query()
            ->where('is_directory_listed', true)
            ->where('status', 'active')
            ->with(['newsletterTypes' => fn ($types) => $types->where('is_external_subscribable', true)]);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(fn ($match) => $match
                ->whereLike('name', $like)
                ->orWhereLike('lodge_number', $like)
                ->orWhereLike('town_city', $like)
                ->orWhereLike('province_region', $like));
        }

        if ($region !== '') {
            $query->where('province_region', $region);
        }

        $clubs = $query->orderBy('name')->get()->map(fn (Club $club) => [
            'id' => $club->id,
            'name' => $club->name,
            'slug' => $club->slug,
            'lodge_number' => $club->lodge_number,
            'province_region' => $club->province_region ?: 'General',
            'town_city' => $club->town_city ?: 'Oxford',
            'logo_url' => $club->logo_url,
            'subscribable_types' => $club->newsletterTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'color' => $type->color,
                'icon' => $type->icon,
                'require_approval' => $type->require_approval,
                'require_home_club_info' => $type->require_home_club_info,
            ])->all(),
        ])->all();

        $regions = Club::where('is_directory_listed', true)
            ->whereNotNull('province_region')
            ->distinct()
            ->orderBy('province_region')
            ->pluck('province_region')
            ->all();

        return [
            'clubs' => $clubs,
            'regions' => $regions,
            'filters' => ['search' => $search, 'region' => $region],
        ];
    }
}
