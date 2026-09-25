<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redirects a lodge sets up by hand, for example from an address on its old website. Unlike
     * page_redirects (kept automatically when a page is renamed) these can point anywhere.
     */
    public function up(): void
    {
        Schema::create('club_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('from_path');
            $table->string('to_url', 2048);
            $table->boolean('is_permanent')->default(true);
            $table->timestamps();

            $table->unique(['club_id', 'from_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_redirects');
    }
};
