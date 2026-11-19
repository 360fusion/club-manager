<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The kinds of inbox notification someone has asked not to be sent to their phone (ClubNotification::PUSH_OPTIONAL).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('push_muted')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_muted');
        });
    }
};
