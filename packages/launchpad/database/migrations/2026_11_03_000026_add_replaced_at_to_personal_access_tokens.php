<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // set when a token is replaced (rotated): its expiry is then the end of its grace period, not its own lifetime
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->timestamp('replaced_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn('replaced_at'));
    }
};
