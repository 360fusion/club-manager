<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_acc_members')) {
            Schema::create('club_acc_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('customer_account_id')->nullable()->constrained('accounting_contacts')->nullOnDelete();

                // Identity & Contact
                $table->string('title', 30)->nullable()->default('Bro');
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->string('postcode', 20)->nullable();

                // Masonic Metadata
                $table->string('masonic_rank', 50)->default('Bro'); // Bro, WBro, VWBro, RWBro, MWBro
                $table->string('grand_rank')->nullable(); // e.g. PAGDC, PJGD
                $table->string('provincial_rank')->nullable(); // e.g. PPrGSuptWks, PPrSGD
                $table->string('grand_lodge_number', 50)->nullable(); // Hermes / Adelphi Member ID

                // Status & Office
                $table->string('membership_status', 30)->default('active'); // active, resigned, honorary, excluded_rule_181, deceased
                $table->string('current_office', 40)->default('member'); // wm, sw, jw, secretary, treasurer, etc.

                // Key Dates
                $table->date('date_of_initiation')->nullable();
                $table->date('date_of_passing')->nullable();
                $table->date('date_of_raising')->nullable();
                $table->date('date_of_joining')->nullable();

                // Financial & Administrative Overrides
                $table->decimal('annual_dues_override', 10, 2)->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['club_id', 'membership_status']);
                $table->index(['club_id', 'current_office']);
                $table->index(['club_id', 'last_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_acc_members');
    }
};
