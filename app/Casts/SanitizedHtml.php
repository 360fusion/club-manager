<?php

namespace App\Casts;

use App\Support\RichTextSanitizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitises rich text on the way into the database.
 *
 * Applied as a cast rather than in each controller so that every write path --
 * including seeders, imports and future controllers -- is covered.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SanitizedHtml implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return RichTextSanitizer::sanitize((string) $value);
    }
}
