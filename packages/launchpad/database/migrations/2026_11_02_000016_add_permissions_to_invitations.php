<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // what the inviter ticked for the person: permission key => true or false, copied to their membership when they accept
            $table->json('permissions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', fn (Blueprint $table) => $table->dropColumn('permissions'));
    }
};
