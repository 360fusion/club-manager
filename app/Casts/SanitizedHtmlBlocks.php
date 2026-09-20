<?php

namespace App\Casts;

use App\Support\RichTextSanitizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Array cast that sanitises the rich-text keys inside a block structure.
 *
 * Page and post blocks are stored as JSON and their `content` is rendered with
 * v-html, so the nested values need the same treatment as a plain HTML column.
 *
 * @implements CastsAttributes<array<int|string, mixed>|null, array<int|string, mixed>|null>
 */
class SanitizedHtmlBlocks implements CastsAttributes
{
    /**
     * Block keys whose values are rendered as HTML.
     */
    private const HTML_KEYS = ['content', 'html', 'body'];

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return null;
        }

        return json_encode($this->sanitizeRecursively($value));
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    private function sanitizeRecursively(array $blocks): array
    {
        foreach ($blocks as $key => $value) {
            if (is_array($value)) {
                $blocks[$key] = $this->sanitizeRecursively($value);

                continue;
            }

            if (is_string($value) && in_array($key, self::HTML_KEYS, true)) {
                $blocks[$key] = RichTextSanitizer::sanitize($value);
            }
        }

        return $blocks;
    }
}
