<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The club is the one that pays the platform, but Cashier's customer columns and subscriptions were made for users, so
 * checkout and the portal could never have worked for a club. Point them at clubs, and keep a log of Stripe's
 * messages so one that arrives twice is only acted on once.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nothing could have been bought through the old shape; if there is a row, a person should look before it is renamed.
        if (DB::table('subscriptions')->exists()) {
            throw new RuntimeException('subscriptions already has rows keyed on user_id; move them to clubs by hand before running this migration.');
        }

        Schema::table('clubs', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'stripe_status']);
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('user_id', 'club_id');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['club_id', 'stripe_status']);
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id')->unique();
            $table->string('type')->index();
            $table->string('customer_id')->nullable()->index();
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'stripe_status']);
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('club_id', 'user_id');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'stripe_status']);
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
