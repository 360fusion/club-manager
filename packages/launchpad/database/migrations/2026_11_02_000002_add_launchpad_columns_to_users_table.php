<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // when the person chose a password (a sign-in with Google gets a random one and may set a first one without a current one)
            $table->timestamp('password_set_at')->nullable();
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            // the end of the grace period of an account that was unverified when verification became required
            $table->timestamp('verify_by')->nullable();
            $table->timestamp('welcome_sent_at')->nullable();
            // null for a customer; 'admin' or 'support' for the team
            $table->string('staff_role', 32)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['staff_role']);
            $table->dropColumn(['password_set_at', 'terms_version', 'terms_accepted_at', 'verify_by', 'welcome_sent_at', 'staff_role']);
        });
    }
};
