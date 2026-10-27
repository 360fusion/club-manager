<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A visiting brother asking to attend one of a lodge's meetings, and the lodge's answer.
     */
    public function up(): void
    {
        Schema::create('club_visit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('meeting_date');
            $table->string('home_lodge_name');
            $table->string('home_lodge_number', 20)->nullable();
            $table->string('rank', 100)->nullable();
            $table->boolean('dining')->default(false);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reply')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status', 'meeting_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_visit_requests');
    }
};
