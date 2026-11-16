<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            // what an owner or admin ticked for this person beyond (or instead of) their role: permission key => true or false
            $table->json('permissions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn('permissions'));
    }
};
