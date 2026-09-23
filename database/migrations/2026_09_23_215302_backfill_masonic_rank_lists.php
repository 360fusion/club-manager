<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give the UGLE record its starting rank lists, and every existing Masonic club a copy of its Grand Lodge's
     * lists (or the starter) where it has none. Safe to run again: nothing already set is changed.
     */
    public function up(): void
    {
        $starter = ['grand_ranks' => config('masonic_ranks.grand', []), 'provincial_ranks' => config('masonic_ranks.provincial', [])];

        $ugle = DB::table('grand_lodges')->where('code', 'ugle')->first();

        if ($ugle) {
            $update = [];

            foreach ($starter as $column => $list) {
                if ($ugle->{$column} === null) {
                    $update[$column] = json_encode($list);
                }
            }

            if ($update !== []) {
                DB::table('grand_lodges')->where('id', $ugle->id)->update($update);
            }
        }

        $masonicTypeIds = DB::table('club_types')->whereIn('code', config('masonic_ranks.club_type_codes', []))->pluck('code', 'id');

        DB::table('clubs')->whereIn('club_type_id', $masonicTypeIds->keys())->orderBy('id')->each(function ($club) use ($starter, $masonicTypeIds) {
            $settings = json_decode($club->settings ?? '[]', true) ?: [];
            $grandLodge = $club->province_id
                ? DB::table('provinces')->join('grand_lodges', 'grand_lodges.id', '=', 'provinces.grand_lodge_id')->where('provinces.id', $club->province_id)->select('grand_lodges.grand_ranks', 'grand_lodges.provincial_ranks')->first()
                : null;
            $starts = in_array($masonicTypeIds[$club->club_type_id], config('masonic_ranks.starter_club_type_codes', []), true);
            $changed = false;

            foreach ($starter as $column => $list) {
                if (array_key_exists($column, $settings)) {
                    continue;
                }

                $master = $grandLodge ? json_decode($grandLodge->{$column} ?? 'null', true) : null;
                $settings[$column] = is_array($master) && $master !== [] ? $master : ($starts ? $list : []);
                $changed = true;
            }

            if ($changed) {
                DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);
            }
        });
    }

    public function down(): void
    {
        // The lists are data now in use by lodges; leave them in place.
    }
};
