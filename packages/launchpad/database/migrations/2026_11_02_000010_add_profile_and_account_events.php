<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable();
            // what a person chose on their Notifications page: key => true or false (config launchpad.accounts.notifications)
            $table->json('notification_choices')->nullable();
        });

        // a person's own recent sign-ins and security changes, shown on their Activity page
        Schema::create('account_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 48);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['timezone', 'notification_choices']));
    }
};
