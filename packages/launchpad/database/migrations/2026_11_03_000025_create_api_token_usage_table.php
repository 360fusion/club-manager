<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what each API token did on each day: calls, and how many of them were errors
        Schema::create('api_token_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('errors')->default(0);

            $table->unique(['token_id', 'day']);
        });

        // the last caller, to spot a key in the wrong hands
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('last_ip', 45)->nullable();
            $table->string('last_user_agent', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn(['last_ip', 'last_user_agent']));
        Schema::dropIfExists('api_token_usage');
    }
};
