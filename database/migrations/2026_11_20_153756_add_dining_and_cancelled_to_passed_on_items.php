<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A lodge on the platform's own summons passed on to other lodges carries its dining (theme, menu, guest price), and
     * is marked cancelled when the lodge cancels or deletes the meeting (PlatformSummons::follow).
     */
    public function up(): void
    {
        Schema::table('passed_on_items', function (Blueprint $table) {
            $table->json('dining')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('passed_on_items', function (Blueprint $table) {
            $table->dropColumn(['dining', 'cancelled_at']);
        });
    }
};
