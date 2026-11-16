<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // no workspaces table without the workspaces module: the late-columns migration adds these if it is turned on afterwards
        foreach (array_filter(['users', config('launchpad.workspaces.table', 'workspaces')], Schema::hasTable(...)) as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('suspended_at')->nullable();
                $table->string('suspended_reason', 500)->nullable(); // for staff only; the customer is not shown it
            });
        }
    }

    public function down(): void
    {
        foreach (array_filter(['users', config('launchpad.workspaces.table', 'workspaces')], Schema::hasTable(...)) as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['suspended_at', 'suspended_reason']));
        }
    }
};
