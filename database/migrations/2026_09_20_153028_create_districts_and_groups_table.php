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
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grand_lodge_id')->nullable()->constrained('grand_lodges')->nullOnDelete();
            $table->string('name');                         // e.g. "District Grand Lodge of Gibraltar"
            $table->string('code')->unique();               // slug-style key e.g. "gibraltar"
            $table->enum('type', ['district', 'group', 'dormant'])->default('district');
            $table->string('region')->nullable();           // e.g. "Europe", "Far East"
            $table->string('country')->nullable();          // host country
            $table->string('website_url')->nullable();
            $table->string('district_grand_master')->nullable();
            $table->string('district_grand_secretary')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('town')->nullable();
            $table->string('county')->nullable();
            $table->string('postcode')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
