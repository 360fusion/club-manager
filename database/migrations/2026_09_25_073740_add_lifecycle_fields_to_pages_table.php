<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled publishing, a footer menu, a per-page header style, a secret preview link and an unpublished
     * draft of a live page (content only: title, search text, share image and elements).
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('is_published');
            $table->timestamp('unpublish_at')->nullable()->after('publish_at');
            $table->boolean('show_in_footer')->default(false)->after('show_in_navigation');
            $table->string('header_style', 16)->default('full')->after('show_in_footer');
            $table->string('preview_token', 40)->nullable()->after('header_style');
            $table->json('draft_blocks')->nullable()->after('preview_token');
            $table->json('draft_content')->nullable()->after('draft_blocks');
            $table->timestamp('draft_saved_at')->nullable()->after('draft_content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['publish_at', 'unpublish_at', 'show_in_footer', 'header_style', 'preview_token', 'draft_blocks', 'draft_content', 'draft_saved_at']);
        });
    }
};
