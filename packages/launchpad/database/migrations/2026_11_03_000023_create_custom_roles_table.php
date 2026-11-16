<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a role a team makes for itself: a name on top of manager or member (its rank), with its own permissions
        Schema::create('custom_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('base_role', 16);
            // only what differs from the base role's permissions in this team: key => bool
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        foreach (['memberships', 'invitations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('custom_role_id')->nullable()->constrained('custom_roles')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['memberships', 'invitations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('custom_role_id');
            });
        }

        Schema::dropIfExists('custom_roles');
    }
};
