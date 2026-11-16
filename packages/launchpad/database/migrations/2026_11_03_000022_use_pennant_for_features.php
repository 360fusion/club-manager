<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel Pennant's table (its database driver), made here so nothing has to be published; the per-workspace table it replaces goes
        if (! Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('scope');
                $table->text('value');
                $table->timestamps();

                $table->unique(['name', 'scope']);
            });
        }

        Schema::dropIfExists('workspace_features');
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
