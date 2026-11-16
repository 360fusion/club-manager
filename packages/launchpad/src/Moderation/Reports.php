<?php

namespace Launchpad\Moderation;

use Illuminate\Database\Eloquent\Model;
use Launchpad\Models\Report;

final class Reports
{
    /** @return array<string, class-string<Model&Reportable>> key => model, from config launchpad.moderation.types */
    public static function types(): array
    {
        return array_filter((array) config('launchpad.moderation.types'), fn ($class) => is_string($class) && is_subclass_of($class, Reportable::class));
    }

    /** @return array<string, string> key => what people pick from */
    public static function reasons(): array
    {
        return array_map(fn ($label) => __($label), (array) config('launchpad.moderation.reasons'));
    }

    public static function typeOf(Model $subject): ?string
    {
        return array_search($subject::class, self::types(), true) ?: null;
    }

    /** A second report of the same thing by the same person, while the first is open, is not counted twice */
    public function file(object $reporter, Model $subject, string $reason, ?string $details): Report
    {
        return Report::firstOrCreate(
            ['reporter_id' => $reporter->getKey(), 'subject_type' => self::typeOf($subject), 'subject_id' => $subject->getKey(), 'status' => 'open'],
            ['reason' => $reason, 'details' => $details],
        );
    }
}
