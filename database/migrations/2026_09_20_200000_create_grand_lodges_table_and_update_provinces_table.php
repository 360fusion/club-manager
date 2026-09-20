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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            if (Schema::hasColumn('provinces', 'grand_lodge_id')) {
                $table->dropForeign(['grand_lodge_id']);
                $table->dropColumn('grand_lodge_id');
            }
        });

        Schema::dropIfExists('grand_lodges');
    }
};
