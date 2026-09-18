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
        Schema::table('newsletter_types', function (Blueprint $table) {
            $table->boolean('is_automated_digest')->default(false)->after('sender_email');
            $table->string('digest_frequency')->default('weekly')->after('is_automated_digest');
            $table->string('digest_send_day')->default('friday')->after('digest_frequency');
            $table->string('digest_send_time')->default('09:00')->after('digest_send_day');
            $table->boolean('include_updates')->default(true)->after('digest_send_time');
            $table->boolean('include_upcoming_meetings')->default(true)->after('include_updates');
            $table->boolean('include_upcoming_events')->default(true)->after('include_upcoming_meetings');
            $table->boolean('include_news_posts')->default(true)->after('include_upcoming_events');
            $table->string('inbound_email_address')->nullable()->after('include_news_posts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletter_types', function (Blueprint $table) {
            $table->dropColumn([
                'is_automated_digest',
                'digest_frequency',
                'digest_send_day',
                'digest_send_time',
                'include_updates',
                'include_upcoming_meetings',
                'include_upcoming_events',
                'include_news_posts',
                'inbound_email_address',
            ]);
        });
    }
};
