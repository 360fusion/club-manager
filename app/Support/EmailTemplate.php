<?php

namespace App\Support;

use App\Models\DefaultEmailTemplate;

/**
 * Fills a superadmin-editable email template. Plain values are escaped; blocks that the app builds itself
 * (lists, payment details) are passed as HTML and inserted as they are. Returns null when the template has
 * been removed, so the caller can fall back to its built-in view.
 */
class EmailTemplate
{
    /**
     * @param  array<string, scalar|null>  $text  placeholder => plain text value
     * @param  array<string, string>  $html  placeholder => trusted HTML block
     * @return array{subject: string, body: string}|null
     */
    public static function render(string $key, array $text, array $html = []): ?array
    {
        $template = DefaultEmailTemplate::where('template_key', $key)->first();

        if (! $template) {
            return null;
        }

        $subject = self::replace($template->subject, array_map(fn ($v) => (string) $v, $text));
        $body = self::replace($template->body_html, array_map(fn ($v) => e((string) $v), $text) + $html);

        return ['subject' => trim(str_replace(["\r", "\n"], ' ', $subject)), 'body' => $body];
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function replace(string $text, array $values): string
    {
        $pairs = [];

        foreach ($values as $name => $value) {
            $pairs['{{'.$name.'}}'] = $value;
        }

        return strtr($text, $pairs);
    }
}
