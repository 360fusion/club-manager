<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Mirrors App\Models\NewsTag::DEFAULTS at the time of writing, so later changes to the
     * constant never alter what this migration does.
     *
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'Charity & Community' => 'rose',
        'Ceremonies & Initiations' => 'indigo',
        'Long Service Awards' => 'amber',
        'Provincial News' => 'purple',
        'Lodge News' => 'sky',
        'Events & Social' => 'emerald',
    ];

    public function up(): void
    {
        Schema::create('news_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->string('color', 20)->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'slug'], 'news_tags_club_slug_unique');
        });

        Schema::create('news_tag_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_tag_id')->constrained('news_tags')->cascadeOnDelete();

            $table->unique(['post_id', 'news_tag_id'], 'news_tag_post_unique');
        });

        $now = now();

        DB::table('clubs')->orderBy('id')->pluck('id')->each(function ($clubId) use ($now) {
            foreach (self::DEFAULTS as $name => $colour) {
                DB::table('news_tags')->insert([
                    'club_id' => $clubId,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'color' => $colour,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_tag_post');
        Schema::dropIfExists('news_tags');
    }
};
