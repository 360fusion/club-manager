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
        Schema::table('club_types', function (Blueprint $table) {
            if (! Schema::hasColumn('club_types', 'terminology')) {
                $table->json('terminology')->nullable()->after('default_settings');
            }
            if (! Schema::hasColumn('club_types', 'rulers_schema')) {
                $table->json('rulers_schema')->nullable()->after('terminology');
            }
        });

        if (! Schema::hasTable('default_officer_roles')) {
            Schema::create('default_officer_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_type_id')->constrained('club_types')->cascadeOnDelete();
                $table->string('title');
                $table->string('short_code');
                $table->integer('rank_level')->default(1);
                $table->boolean('is_executive')->default(false);
                $table->string('category')->default('progressive');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('default_ranks')) {
            Schema::create('default_ranks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_type_id')->constrained('club_types')->cascadeOnDelete();
                $table->string('title');
                $table->string('abbreviation');
                $table->integer('hierarchy_order')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('default_email_templates')) {
            Schema::create('default_email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('template_key')->unique();
                $table->string('name');
                $table->string('subject');
                $table->text('body_html');
                $table->json('available_placeholders')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_email_templates');
        Schema::dropIfExists('default_ranks');
        Schema::dropIfExists('default_officer_roles');

        Schema::table('club_types', function (Blueprint $table) {
            if (Schema::hasColumn('club_types', 'rulers_schema')) {
                $table->dropColumn('rulers_schema');
            }
            if (Schema::hasColumn('club_types', 'terminology')) {
                $table->dropColumn('terminology');
            }
        });
    }
};
