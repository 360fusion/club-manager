<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the language a person chose for the site and its emails (null: follow the browser)
        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 12)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
