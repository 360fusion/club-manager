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
        if (Schema::hasTable('club_acc_charity_collections')) {
            Schema::table('club_acc_charity_collections', function (Blueprint $table) {
                if (! Schema::hasColumn('club_acc_charity_collections', 'donor_member_id')) {
                    $table->foreignId('donor_member_id')->nullable()->after('witnessed_by_member_id')->constrained('club_acc_members')->nullOnDelete();
                }
                if (! Schema::hasColumn('club_acc_charity_collections', 'donor_name')) {
                    $table->string('donor_name')->nullable()->after('donor_member_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('club_acc_charity_collections')) {
            Schema::table('club_acc_charity_collections', function (Blueprint $table) {
                if (Schema::hasColumn('club_acc_charity_collections', 'donor_member_id')) {
                    $table->dropForeign(['donor_member_id']);
                    $table->dropColumn('donor_member_id');
                }
                if (Schema::hasColumn('club_acc_charity_collections', 'donor_name')) {
                    $table->dropColumn('donor_name');
                }
            });
        }
    }
};
