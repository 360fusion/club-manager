<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Secretary keeps the membership roll and the candidates, so manage_members now includes the Secretary role. Clubs
 * whose saved permission matrix still holds the old default (owners and admins only) get it too; a club that chose its
 * own roles for Members is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('clubs')->whereNotNull('settings')->orderBy('id')->each(function ($club) {
            $settings = json_decode($club->settings, true);
            $roles = $settings['permission_matrix']['manage_members']['roles'] ?? null;

            if (! is_array($roles) || array_diff($roles, ['owner', 'admin']) !== [] || count($roles) !== 2) {
                return;
            }

            $settings['permission_matrix']['manage_members']['roles'] = ['owner', 'admin', 'coach', 'secretary'];
            DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        // Not undone: taking the roll away from Secretaries again is a choice each club makes on its settings screen.
    }
};
