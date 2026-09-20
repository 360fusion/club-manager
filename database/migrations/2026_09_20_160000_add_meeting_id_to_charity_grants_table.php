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
        Schema::table('club_acc_charity_grants', function (Blueprint $table) {
            if (! Schema::hasColumn('club_acc_charity_grants', 'meeting_id')) {
                $table->foreignId('meeting_id')->nullable()->after('committee_meeting_id')->constrained('meetings')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_acc_charity_grants', function (Blueprint $table) {
            if (Schema::hasColumn('club_acc_charity_grants', 'meeting_id')) {
                $table->dropForeign(['meeting_id']);
                $table->dropColumn('meeting_id');
            }
        });
    }
};
