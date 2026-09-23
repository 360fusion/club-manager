<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masonic_halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('town', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('postcode', 30)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website_url')->nullable();
            $table->timestamps();

            $table->index(['province_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masonic_halls');
    }
};
