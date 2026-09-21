<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 100);
            $table->string('subject');
            $table->text('body_html');
            $table->timestamps();

            $table->unique(['club_id', 'template_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_email_templates');
    }
};
