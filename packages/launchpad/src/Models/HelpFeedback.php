<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;

/** The answer to "Was this helpful?" on a help article. Nothing says who answered. */
class HelpFeedback extends Model
{
    protected $table = 'help_feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }
}
