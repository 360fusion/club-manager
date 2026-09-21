<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->string('organisation', 150)->nullable();
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->text('internal_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('internal_note');
        });

        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropColumn('organisation');
        });
    }
};
