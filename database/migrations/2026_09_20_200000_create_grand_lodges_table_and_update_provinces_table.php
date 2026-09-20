<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('grand_lodges')) {
            Schema::create('grand_lodges', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('short_name')->nullable();
                $table->string('country')->default('England');
                $table->string('website_url')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('provinces', function (Blueprint $table) {
            if (! Schema::hasColumn('provinces', 'grand_lodge_id')) {
                $table->foreignId('grand_lodge_id')->nullable()->after('id')->constrained('grand_lodges')->nullOnDelete();
            }
        });

        // districts is created earlier, before grand_lodges exists, so it links here.
        if (Schema::hasColumn('districts', 'grand_lodge_id') && ! $this->hasForeignKey('districts', 'grand_lodge_id')) {
            Schema::table('districts', function (Blueprint $table) {
                $table->foreign('grand_lodge_id')->references('id')->on('grand_lodges')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('districts') && $this->hasForeignKey('districts', 'grand_lodge_id')) {
            Schema::table('districts', function (Blueprint $table) {
                $table->dropForeign(['grand_lodge_id']);
            });
        }

        Schema::table('provinces', function (Blueprint $table) {
            if (Schema::hasColumn('provinces', 'grand_lodge_id')) {
                $table->dropForeign(['grand_lodge_id']);
                $table->dropColumn('grand_lodge_id');
            }
        });

        Schema::dropIfExists('grand_lodges');
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
