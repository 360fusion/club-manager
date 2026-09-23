<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            $table->timestamp('invitation_reminded_at')->nullable()->after('invitation_accepted_at');
            $table->foreignId('invited_by')->nullable()->after('invitation_reminded_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->index(['club_id', 'email'], 'club_acc_members_club_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->dropIndex('club_acc_members_club_email_index');
        });

        Schema::table('club_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn('invitation_reminded_at');
        });
    }
};
