<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Launchpad\Launchpad;
use Launchpad\Moderation\Reports;

/** Somebody's report of content they think breaks the rules, and what staff did about it */
class Report extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'handled_by');
    }

    /** The reported content, if it still exists */
    public function subject(): ?Model
    {
        $class = Reports::types()[$this->subject_type] ?? null;

        return $class ? $class::find($this->subject_id) : null;
    }
}
