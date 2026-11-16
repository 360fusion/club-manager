<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a row overrides the default wording of one mail (Launchpad\Mail\Templates); no row means the default
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('subject', 255);
            $table->text('body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
