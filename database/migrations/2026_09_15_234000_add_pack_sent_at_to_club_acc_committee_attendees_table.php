<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_acc_committee_attendees', function (Blueprint $table) {
            $table->dateTime('pack_sent_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('club_acc_committee_attendees', function (Blueprint $table) {
            $table->dropColumn('pack_sent_at');
        });
    }
};
