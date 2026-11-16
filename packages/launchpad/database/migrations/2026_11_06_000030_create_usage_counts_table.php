<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // how much of each meter (config launchpad.metering.meters) a team has used in a period: a calendar month, or one row for ever
        Schema::create('usage_counts', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->string('meter', 64);
            $table->date('period');
            $table->unsignedBigInteger('used')->default(0);

            $table->unique(['workspace_id', 'meter', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counts');
    }
};
