<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Club::$email was already read in two places as the fallback contact
     * address, but no such column existed, so it always resolved to null.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'email')) {
                $table->string('email')->nullable()->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
