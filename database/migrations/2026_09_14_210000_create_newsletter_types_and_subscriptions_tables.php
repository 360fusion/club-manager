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
        // Add Directory & Location fields to Clubs if not already present
        Schema::table('clubs', function (Blueprint $table) {
            if (!Schema::hasColumn('clubs', 'province_region')) {
                $table->string('province_region')->nullable();
            }
            if (!Schema::hasColumn('clubs', 'lodge_number')) {
                $table->string('lodge_number')->nullable();
            }
            if (!Schema::hasColumn('clubs', 'town_city')) {
                $table->string('town_city')->nullable();
            }
            if (!Schema::hasColumn('clubs', 'is_directory_listed')) {
                $table->boolean('is_directory_listed')->default(true);
            }
        });

        // Newsletter Types / Subscription Channels
        if (!Schema::hasTable('newsletter_types')) {
            Schema::create('newsletter_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('color')->default('#4f46e5'); // Hex color badge
                $table->string('icon')->default('✉️');
                $table->boolean('is_external_subscribable')->default(true); // Open to visiting brethren / public
                $table->boolean('require_approval')->default(false); // Secretary approval needed for external subs
                $table->boolean('is_mandatory')->default(false); // Internal members cannot unsubscribe
                $table->boolean('require_home_club_info')->default(true);
                $table->json('default_roles')->nullable(); // Default target roles e.g. ["member"]
                $table->string('sender_name')->nullable();
                $table->string('sender_email')->nullable();
                $table->timestamps();

                $table->unique(['club_id', 'slug']);
            });
        }

        // Newsletter Subscriptions (Explicit Subscribers)
        if (!Schema::hasTable('newsletter_subscriptions')) {
            Schema::create('newsletter_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
                $table->foreignId('newsletter_type_id')->constrained('newsletter_types')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('email');
                $table->string('name')->nullable();
                $table->string('rank')->nullable();
                $table->string('home_club_name')->nullable();
                $table->string('home_club_number')->nullable();
                $table->string('status')->default('active'); // active, pending_approval, unsubscribed, rejected
                $table->timestamp('subscribed_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();

                $table->index(['club_id', 'newsletter_type_id', 'status']);
                $table->index(['email', 'status']);
            });
        }

        // Add newsletter_type_id to newsletters table
        Schema::table('newsletters', function (Blueprint $table) {
            if (!Schema::hasColumn('newsletters', 'newsletter_type_id')) {
                $table->foreignId('newsletter_type_id')->nullable()->after('club_id')->constrained('newsletter_types')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            if (Schema::hasColumn('newsletters', 'newsletter_type_id')) {
                $table->dropForeign(['newsletter_type_id']);
                $table->dropColumn('newsletter_type_id');
            }
        });

        Schema::dropIfExists('newsletter_subscriptions');
        Schema::dropIfExists('newsletter_types');
    }
};
