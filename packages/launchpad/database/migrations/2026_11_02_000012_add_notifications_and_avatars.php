<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // where the person's uploaded photo is kept on the avatar disk (config launchpad.accounts.avatar_disk)
            $table->string('avatar_path')->nullable();
        });

        // Laravel's own table for database notifications, unless the app already made it (php artisan make:notifications-table)
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
        // the notifications table may be the app's own: it is left alone
    }
};
