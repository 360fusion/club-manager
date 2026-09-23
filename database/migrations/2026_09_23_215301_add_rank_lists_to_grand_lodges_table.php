<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grand_lodges', function (Blueprint $table) {
            $table->json('grand_ranks')->nullable();
            $table->json('provincial_ranks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('grand_lodges', function (Blueprint $table) {
            $table->dropColumn(['grand_ranks', 'provincial_ranks']);
        });
    }
};
