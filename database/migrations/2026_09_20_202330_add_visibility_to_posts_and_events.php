<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * New content defaults to club members only. Rows that already exist were
     * shown publicly, so they are backfilled as public to keep behaviour the same.
     * RSVPs have always been limited to club members, so that audience stays narrow.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('visibility')->default('club')->after('status');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('visibility')->default('club')->after('status');
            $table->string('rsvp_audience')->default('club')->after('visibility');
        });

        DB::table('posts')->update(['visibility' => 'public']);
        DB::table('events')->update(['visibility' => 'public']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'rsvp_audience']);
        });
    }
};
