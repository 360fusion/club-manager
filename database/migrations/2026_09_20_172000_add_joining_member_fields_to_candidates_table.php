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
        if (Schema::hasTable('club_acc_candidates')) {
            Schema::table('club_acc_candidates', function (Blueprint $table) {
                if (! Schema::hasColumn('club_acc_candidates', 'candidate_type')) {
                    $table->string('candidate_type')->default('new_candidate')->after('stage');
                }
                if (! Schema::hasColumn('club_acc_candidates', 'grand_lodge_number')) {
                    $table->string('grand_lodge_number')->nullable()->after('candidate_type');
                }
                if (! Schema::hasColumn('club_acc_candidates', 'mother_lodge_info')) {
                    $table->string('mother_lodge_info')->nullable()->after('grand_lodge_number');
                }
                if (! Schema::hasColumn('club_acc_candidates', 'clearance_certificate_path')) {
                    $table->string('clearance_certificate_path')->nullable()->after('mother_lodge_info');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('club_acc_candidates')) {
            Schema::table('club_acc_candidates', function (Blueprint $table) {
                $cols = array_filter(['candidate_type', 'grand_lodge_number', 'mother_lodge_info', 'clearance_certificate_path'], function ($col) {
                    return Schema::hasColumn('club_acc_candidates', $col);
                });
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
