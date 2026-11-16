<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A workspaces table made after the sync and suspension migrations ran (the workspaces module turned on later) has missed their columns: they are added
 * here. Where they are already there, which is every install that had workspaces from the start, nothing happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('launchpad.workspaces.table', 'workspaces'), function (Blueprint $table) {
            Schema::hasColumn(config('launchpad.workspaces.table', 'workspaces'), 'legacy_id') || $table->string('legacy_id', 64)->nullable()->index();
            Schema::hasColumn(config('launchpad.workspaces.table', 'workspaces'), 'suspended_at') || $table->timestamp('suspended_at')->nullable();
            Schema::hasColumn(config('launchpad.workspaces.table', 'workspaces'), 'suspended_reason') || $table->string('suspended_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        // the columns belong to the migrations above; this only ever filled gaps
    }
};
