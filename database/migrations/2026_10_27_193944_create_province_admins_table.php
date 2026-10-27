<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People a superadmin has made admins of a province: they review suggested fixes to its lodges' listings.
     */
    public function up(): void
    {
        Schema::create('province_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['province_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('province_admins');
    }
};
