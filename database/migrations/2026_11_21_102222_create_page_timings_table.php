<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How long pages take on members' own phones and computers (Support/pageTimings.js), for the superadmin's page
     * speed report. No person or address is kept: only which page, how it was opened and how long it took.
     */
    public function up(): void
    {
        Schema::create('page_timings', function (Blueprint $table) {
            $table->id();
            $table->string('page', 80);
            $table->string('kind', 10);
            $table->unsignedInteger('ms');
            $table->unsignedInteger('server_ms')->nullable();
            $table->boolean('cached')->default(false);
            $table->string('device', 10);
            $table->boolean('installed')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_timings');
    }
};
