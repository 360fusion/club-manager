<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where each hall is, looked up from its postcode by `php artisan halls:geocode`, for the directory's distance search.
     */
    public function up(): void
    {
        Schema::table('masonic_halls', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable()->after('postcode');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('masonic_halls', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
