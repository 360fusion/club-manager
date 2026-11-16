<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logged_errors', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique(); // the class, file and line: one row per error, however often it happens
            $table->string('class');
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('method', 10)->nullable();
            $table->foreignId('user_id')->nullable();
            $table->text('trace')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logged_errors');
    }
};
