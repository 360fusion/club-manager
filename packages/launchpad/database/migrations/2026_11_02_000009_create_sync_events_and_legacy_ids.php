<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what the app has to tell another service, in order: written in the same request that changed something, delivered by launchpad:sync
        Schema::create('sync_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('type', 64);
            $table->json('payload');
            // pending, delivered or failed (gave up after max_attempts)
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'available_at']);
        });

        // the id a person or workspace had in the system this app replaced, so the two can be matched while both run
        Schema::table('users', function (Blueprint $table) {
            $table->string('legacy_id', 64)->nullable()->index();
        });
        // an app without the workspaces module has no such table: the late-columns migration adds this if it is turned on afterwards
        Schema::hasTable(config('launchpad.workspaces.table', 'workspaces')) && Schema::table(config('launchpad.workspaces.table', 'workspaces'), function (Blueprint $table) {
            $table->string('legacy_id', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::hasTable(config('launchpad.workspaces.table', 'workspaces')) && Schema::hasColumn(config('launchpad.workspaces.table', 'workspaces'), 'legacy_id') && Schema::table(config('launchpad.workspaces.table', 'workspaces'), function (Blueprint $table) {
            $table->dropIndex(['legacy_id']);
            $table->dropColumn('legacy_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['legacy_id']);
            $table->dropColumn('legacy_id');
        });
        Schema::dropIfExists('sync_events');
    }
};
