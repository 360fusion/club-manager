<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->cascadeOnDelete();
            // the ids of the folders above this one, as /1/5/ (empty at the top): a whole branch is found with one LIKE
            $table->string('path')->default('');
            $table->string('name');
            // the lower-cased name: two folders in one place cannot differ only by case
            $table->string('name_key');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // what was trashed in one go (a folder and all inside it) comes back in one go
            $table->uuid('trash_batch')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            // not unique on (workspace_id, parent_id, name_key): a top-level folder has a NULL parent, which never collides, and trashed folders keep their
            // names. Library checks the live siblings instead.
            $table->index(['workspace_id', 'parent_id']);
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40);
            // a random name: what was uploaded is never served from a path the uploader chose
            $table->string('path');
            $table->string('name');
            $table->string('name_key');
            $table->string('mime', 120);
            // image, video, audio, document or other
            $table->string('kind', 20);
            $table->unsignedBigInteger('size');
            $table->string('hash', 64);
            $table->string('alt', 500)->nullable();
            $table->text('notes')->nullable();
            // the Files-page row this was imported from
            $table->unsignedBigInteger('legacy_file_id')->nullable()->unique();
            $table->uuid('trash_batch')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'hash']);
            $table->index(['workspace_id', 'folder_id']);
        });

        Schema::create('media_tags', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->string('name');
            $table->string('name_key');

            $table->unique(['workspace_id', 'name_key']);
        });

        Schema::create('media_file_tag', function (Blueprint $table) {
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->foreignId('media_tag_id')->constrained('media_tags')->cascadeOnDelete();

            $table->primary(['media_file_id', 'media_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_file_tag');
        Schema::dropIfExists('media_tags');
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('media_folders');
    }
};
