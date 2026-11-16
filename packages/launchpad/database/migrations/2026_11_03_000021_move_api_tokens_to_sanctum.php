<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sanctum's own table, with the workspace a token is for; an app that has already published Sanctum's migration just gets the column
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('personal_access_tokens', 'workspace_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->foreignId('workspace_id')->nullable()->constrained(config('launchpad.workspaces.table', 'workspaces'))->cascadeOnDelete();
            });
        }

        // the old tokens were in another form (a hash with no id) and cannot be carried over: people make new ones
        Schema::dropIfExists('api_tokens');
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
