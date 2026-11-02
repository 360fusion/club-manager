<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The waiting-list email said "(1 people)". The number now comes with its own word ("1 person", "3 people"), so the
 * wording after {{people}} goes, in the platform wording and in any lodge's own copy of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['default_email_templates', 'club_email_templates'] as $table) {
            DB::table($table)->where('template_key', 'event_place_available')->where('body_html', 'like', '%{{people}} people%')
                ->get(['id', 'body_html'])
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)->update(['body_html' => str_replace('{{people}} people', '{{people}}', $row->body_html)]));
        }
    }

    public function down(): void
    {
        // The earlier wording cannot be told apart from wording that was written this way on purpose, so it is left as it is.
    }
};
