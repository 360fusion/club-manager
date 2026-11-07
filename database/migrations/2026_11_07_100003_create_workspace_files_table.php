<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_files', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40);
            // a random name: what was uploaded is never served from a path the uploader chose
            $table->string('path');
            $table->string('name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_files');
    }
};
