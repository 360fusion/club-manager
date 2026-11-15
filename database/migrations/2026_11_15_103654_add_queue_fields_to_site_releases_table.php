<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A version now exists from the moment it is asked for (status `queued`), so the Publish screen can show it waiting,
     * building page by page, then live.
     */
    public function up(): void
    {
        Schema::table('site_releases', function (Blueprint $table) {
            // built on its own (after edits, or nightly) rather than by someone pressing Publish now
            $table->boolean('automatic')->default(false)->after('status');
            // when it may start building: 30 minutes after the last edit for an automatic one, straight away otherwise
            $table->timestamp('scheduled_for')->nullable()->after('automatic');
            $table->unsignedInteger('pages_total')->nullable()->after('page_count');
        });
    }

    public function down(): void
    {
        Schema::table('site_releases', function (Blueprint $table) {
            $table->dropColumn(['automatic', 'scheduled_for', 'pages_total']);
        });
    }
};
