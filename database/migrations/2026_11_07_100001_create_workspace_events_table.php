<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what happened in a team, for its admins to read: "Sam invited ana@example.com as Manager"
        Schema::create('workspace_events', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // kept as it was written, so the line still reads when the person's account is gone
            $table->string('actor_name');
            $table->string('action', 64);
            $table->string('summary', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_events');
    }
};
