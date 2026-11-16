<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('launchpad.workspaces.table', 'workspaces'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // 'personal' (one per person, made at sign-up) or 'business'
            $table->string('kind', 16)->default('business');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('timezone', 64)->default('UTC');
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // owner, admin, manager or member (Launchpad\Workspaces\Role)
            $table->string('role', 16)->default('member');
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->string('email');
            $table->string('role', 16)->default('member');
            // only the hash is kept: the link in the mail is the one place the token exists
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('current_workspace_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('current_workspace_id');
        });
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists(config('launchpad.workspaces.table', 'workspaces'));
    }
};
