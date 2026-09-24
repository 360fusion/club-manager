<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodge_follows', function (Blueprint $table) {
            $table->boolean('notify_summons')->default(false)->after('in_calendar');
        });
    }

    public function down(): void
    {
        Schema::table('lodge_follows', function (Blueprint $table) {
            $table->dropColumn('notify_summons');
        });
    }
};
