<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_acc_members', function (Blueprint $table) {
            if (! Schema::hasColumn('club_acc_members', 'middle_names')) {
                $table->string('middle_names')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('club_acc_members', 'preferred_name')) {
                $table->string('preferred_name')->nullable()->after('last_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->dropColumn(['middle_names', 'preferred_name']);
        });
    }
};
