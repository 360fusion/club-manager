<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A browser that turned on notifications from the website (platform `web`): its push subscription (the push
     * service's address and the browser's keys), encrypted. Its `token` is a hash of the address.
     */
    public function up(): void
    {
        Schema::table('push_devices', function (Blueprint $table) {
            $table->text('subscription')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('push_devices', function (Blueprint $table) {
            $table->dropColumn('subscription');
        });
    }
};
