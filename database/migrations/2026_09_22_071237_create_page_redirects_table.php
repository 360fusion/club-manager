<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keeps an old page slug working as a redirect after the page is renamed, so bookmarks, search
     * engines and links from elsewhere are never suddenly 404s.
     */
    public function up(): void
    {
        Schema::create('page_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('old_slug');
            $table->timestamps();

            $table->unique(['club_id', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_redirects');
    }
};
