<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A page can carry its own picture for link previews (Facebook, WhatsApp, Messages) and can ask
     * search engines to leave it out.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('share_image', 2048)->nullable()->after('meta_description');
            $table->boolean('noindex')->default(false)->after('share_image');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['share_image', 'noindex']);
        });
    }
};
