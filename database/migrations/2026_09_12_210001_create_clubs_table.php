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
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_type_id')->constrained('club_types')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
