<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what a member of staff did, in order; never edited, never deleted by the app (admin-log:prune removes the old ones)
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->string('summary', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // the admin's own authenticator-app step (Filament's), apart from the site's two_factor_* columns: the admin signs in on its own guard
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            // set when the account is closed: it is removed for good after the retention period, and can be restored until then
            $table->timestamp('deletion_pending_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deletion_pending_at']);
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'deletion_pending_at']);
        });
        Schema::dropIfExists('admin_actions');
    }
};
