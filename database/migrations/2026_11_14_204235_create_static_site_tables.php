<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One built copy of a club's public website as plain files (see App\Services\StaticSite\StaticSiteBuilder).
        Schema::create('site_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // building, ready or failed
            $table->string('status', 16)->default('building');
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('error', 1000)->nullable();
            // when it was last made the live version on this server
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'id']);
        });

        // Somewhere a club sends its built website: an FTP(S) server or a GitHub repository. Config is encrypted.
        Schema::create('site_deploy_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('name', 120);
            $table->text('config');
            $table->timestamps();
        });

        // Append-only: every push of a release to a target, and what happened.
        Schema::create('site_deploys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_deploy_target_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // queued, running, done or failed
            $table->string('status', 16)->default('queued');
            $table->unsignedInteger('files_sent')->default(0);
            $table->unsignedInteger('files_deleted')->default(0);
            $table->string('message', 1000)->nullable();
            $table->timestamps();

            $table->index(['club_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_deploys');
        Schema::dropIfExists('site_deploy_targets');
        Schema::dropIfExists('site_releases');
    }
};
