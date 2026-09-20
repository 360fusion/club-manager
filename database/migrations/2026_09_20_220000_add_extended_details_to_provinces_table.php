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
        Schema::table('provinces', function (Blueprint $table) {
            if (! Schema::hasColumn('provinces', 'provincial_grand_master')) {
                $table->string('provincial_grand_master')->nullable()->after('website_url');
            }
            if (! Schema::hasColumn('provinces', 'provincial_grand_secretary')) {
                $table->string('provincial_grand_secretary')->nullable()->after('provincial_grand_master');
            }
            if (! Schema::hasColumn('provinces', 'address_line_1')) {
                $table->string('address_line_1')->nullable()->after('provincial_grand_secretary');
            }
            if (! Schema::hasColumn('provinces', 'address_line_2')) {
                $table->string('address_line_2')->nullable()->after('address_line_1');
            }
            if (! Schema::hasColumn('provinces', 'town')) {
                $table->string('town')->nullable()->after('address_line_2');
            }
            if (! Schema::hasColumn('provinces', 'county')) {
                $table->string('county')->nullable()->after('town');
            }
            if (! Schema::hasColumn('provinces', 'postcode')) {
                $table->string('postcode')->nullable()->after('county');
            }
            if (! Schema::hasColumn('provinces', 'telephone')) {
                $table->string('telephone')->nullable()->after('postcode');
            }
            if (! Schema::hasColumn('provinces', 'email')) {
                $table->string('email')->nullable()->after('telephone');
            }
            if (! Schema::hasColumn('provinces', 'twitter_url')) {
                $table->string('twitter_url')->nullable()->after('email');
            }
            if (! Schema::hasColumn('provinces', 'facebook_url')) {
                $table->string('facebook_url')->nullable()->after('twitter_url');
            }
            if (! Schema::hasColumn('provinces', 'description')) {
                $table->text('description')->nullable()->after('facebook_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->dropColumn([
                'provincial_grand_master',
                'provincial_grand_secretary',
                'address_line_1',
                'address_line_2',
                'town',
                'county',
                'postcode',
                'telephone',
                'email',
                'twitter_url',
                'facebook_url',
                'description',
            ]);
        });
    }
};
