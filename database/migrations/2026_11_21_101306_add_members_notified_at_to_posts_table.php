<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a post's members were told about it. Posts already live count as told, so the scheduled announcements
     * (app:announce-scheduled-posts) only pick up news still waiting for its time.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('members_notified_at')->nullable();
        });

        DB::table('posts')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->update(['members_notified_at' => DB::raw('COALESCE(published_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('members_notified_at');
        });
    }
};
