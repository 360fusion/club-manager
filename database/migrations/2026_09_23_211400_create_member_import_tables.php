<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_acc_member_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('file_hash', 64);
            $table->string('stored_path')->nullable();
            $table->string('status', 20)->default('staged'); // staged, imported, undone, cancelled
            $table->json('headers')->nullable();
            $table->json('mapping')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('possible_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('result')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'file_hash']);
        });

        Schema::create('club_acc_member_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('club_acc_member_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->string('status', 20); // new, duplicate, possible_duplicate, error
            $table->string('match_type', 30)->nullable(); // email, grand_lodge_number, name, email_ambiguous, conflict, file
            $table->foreignId('match_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
            $table->unsignedInteger('duplicate_of_row')->nullable();
            $table->string('action', 20)->default('skip'); // create, skip, fill, overwrite
            $table->json('raw')->nullable(); // the original cells, kept only for rows with errors so they can be downloaded and fixed
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->string('outcome', 20)->nullable(); // created, updated, skipped, failed
            $table->string('outcome_note')->nullable();
            $table->foreignId('created_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
            $table->json('previous_values')->nullable();
            $table->json('applied_values')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'status']);
            $table->index(['import_id', 'row_number']);
        });

        Schema::create('club_acc_member_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('header_signature', 64);
            $table->json('mapping'); // normalised header => field key
            $table->timestamps();

            $table->unique(['club_id', 'name']);
            $table->index(['club_id', 'header_signature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_acc_member_import_mappings');
        Schema::dropIfExists('club_acc_member_import_rows');
        Schema::dropIfExists('club_acc_member_imports');
    }
};
