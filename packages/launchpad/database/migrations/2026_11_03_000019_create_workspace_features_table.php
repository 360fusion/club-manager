<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a feature switched on or off for one workspace, over whatever the plan and the admin's global choice say
        Schema::create('workspace_features', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->string('feature', 80);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['workspace_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_features');
    }
};
