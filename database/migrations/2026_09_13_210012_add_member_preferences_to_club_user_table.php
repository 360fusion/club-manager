<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            $table->string('phone', 100)->nullable()->after('status');
            $table->string('emergency_contact', 255)->nullable()->after('phone');
            $table->text('dietary_notes')->nullable()->after('emergency_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            $table->dropColumn(['phone', 'emergency_contact', 'dietary_notes']);
        });
    }
};
