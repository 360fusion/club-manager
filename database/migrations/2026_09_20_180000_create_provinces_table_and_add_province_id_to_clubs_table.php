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
        if (! Schema::hasTable('provinces')) {
            Schema::create('provinces', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('region')->nullable();
                $table->string('website_url')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'province_id')) {
                $table->foreignId('province_id')->nullable()->after('club_type_id')->constrained('provinces')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'province_id')) {
                $table->dropForeign(['province_id']);
                $table->dropColumn('province_id');
            }
        });

        Schema::dropIfExists('provinces');
    }
};
