<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodge_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('url', 500);
            $table->boolean('is_search')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('content_hash', 40)->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();

            $table->unique(['lodge_id', 'kind']);
            $table->index('url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodge_sources');
    }
};
