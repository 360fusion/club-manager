<?php

namespace App\Support;

/**
 * Builds CSV rows that are safe to open in a spreadsheet.
 *
 * A cell starting with = + - @ (or a tab or carriage return) is run as a formula
 * by Excel and Sheets, so a member called `=HYPERLINK(...)` could attack whoever
 * exports the roster. Such cells get a leading apostrophe. Real numbers are left alone.
 */
class Csv
{
    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, mixed>
     */
    public static function safe(array $cells): array
    {
        return array_map(function ($cell) {
            if (is_string($cell) && $cell !== '' && ! is_numeric($cell) && str_contains("=+-@\t\r", $cell[0])) {
                return "'".$cell;
            }

            return $cell;
        }, $cells);
    }

    /**
     * One properly quoted CSV line, including the trailing newline.
     *
     * @param  array<int, mixed>  $cells
     */
    public static function line(array $cells): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::safe($cells));
        rewind($handle);
        $line = stream_get_contents($handle);
        fclose($handle);

        return $line;
    }
}
