<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodge_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('masonic_hall_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('regular');
            $table->string('occurrence', 10);
            $table->string('day_of_week', 10);
            $table->json('months');
            $table->string('start_time', 5)->nullable();
            $table->string('source', 10)->default('import');
            $table->timestamps();

            $table->index('lodge_id');
            $table->index('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodge_schedules');
    }
};
