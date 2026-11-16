<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('launchpad.workspaces.table', 'workspaces'), function (Blueprint $table) {
            // what the owner changed for a role in this team: role => [permission key => true or false]; only what differs from the defaults
            $table->json('role_permissions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('launchpad.workspaces.table', 'workspaces'), fn (Blueprint $table) => $table->dropColumn('role_permissions'));
    }
};
