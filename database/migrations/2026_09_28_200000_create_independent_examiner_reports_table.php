<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('independent_examiner_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->unsignedSmallInteger('financial_year');
            $table->foreignId('examiner_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('examined_at')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('independent_examiner_reports');
    }
};
