<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the wording is kept per language: the edits made so far were in the app's language (what mail is sent in when nobody has one), and a key may now have one row per language
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('locale', 12)->default('en')->after('key');
            $table->dropUnique(['key']);
            $table->unique(['key', 'locale']);
        });

        DB::table('email_templates')->update(['locale' => (string) config('app.locale')]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('locale', '!=', 'en')->delete();

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['key', 'locale']);
            $table->unique('key');
            $table->dropColumn('locale');
        });
    }
};
