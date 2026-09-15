<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            $table->string('committee_role', 50)->nullable()->after('rank');
        });
    }

    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            $table->dropColumn('committee_role');
        });
    }
};
