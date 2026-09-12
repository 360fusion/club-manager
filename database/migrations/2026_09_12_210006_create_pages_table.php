<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->jsonb('blocks')->default('[]');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_homepage')->default(false);
            $table->boolean('show_in_navigation')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['club_id', 'slug']);
            $table->index(['club_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
