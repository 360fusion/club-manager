<?php

namespace Launchpad\Site;

use Illuminate\Support\Str;

/**
 * Fills {{tokens}} in a page's Markdown. A token with no value is left as it is, and reported, so a page never goes live with a hole in it
 * unseen (launchpad:doctor says which pages still have one). {{subprocessors_table}} and {{cookies_table}} are tables the app fills in.
 */
final class Tokens
{
    private const BLOCKS = ['subprocessors_table', 'cookies_table'];

    /** @return array{0: string, 1: list<string>} the text and the tokens that had no value */
    public static function fill(string $markdown): array
    {
        $values = Site::tokens();
        $missing = [];

        $text = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function (array $m) use ($values, &$missing) {
            $key = $m[1];

            if (in_array($key, self::BLOCKS, true)) {
                return self::table($key);
            }

            if (isset($values[$key])) {
                return $values[$key];
            }

            $missing[] = $key;

            return $m[0];
        }, $markdown) ?? $markdown;

        return [$text, array_values(array_unique($missing))];
    }

    private static function table(string $key): string
    {
        $rows = (array) config($key === 'cookies_table' ? 'launchpad.site.cookies' : 'launchpad.site.subprocessors', []);

        if ($rows === []) {
            return '*None listed yet.*';
        }

        $headers = $key === 'cookies_table' ? ['Name', 'What it is for', 'Kind', 'How long'] : ['Provider', 'What they do', 'Where'];
        $keys = $key === 'cookies_table' ? ['name', 'purpose', 'category', 'duration'] : ['name', 'purpose', 'location'];

        $cell = fn ($value) => Str::of((string) $value)->replace(['|', "\n"], [' ', ' '])->toString();
        $lines = ['| '.implode(' | ', $headers).' |', '|'.str_repeat(' --- |', count($headers))];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(fn ($k) => $cell($row[$k] ?? ''), $keys)).' |';
        }

        return implode("\n", $lines);
    }
}
