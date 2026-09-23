<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Reads an uploaded spreadsheet export into a header row and data rows, whatever program saved it.
 *
 * Handles what real files throw at a plain fgetcsv loop: a byte-order mark, Windows-1252 text from Excel,
 * comma / semicolon / tab separators, quoted cells that contain line breaks, and blank lines.
 */
class CsvReader
{
    /**
     * @return array{delimiter: string, headers: array<int, string>, rows: array<int, array<int, string>>}
     *
     * @throws InvalidArgumentException when the file cannot be read or has more than $maxRows data rows
     */
    public static function read(string $path, int $maxRows = 5000): array
    {
        $content = is_file($path) ? file_get_contents($path) : false;

        if ($content === false || trim($content) === '') {
            throw new InvalidArgumentException('The file is empty or could not be read.');
        }

        $content = self::toUtf8($content);
        $delimiter = self::detectDelimiter($content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headers = null;
        $rows = [];

        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $cells = array_map(fn ($cell) => trim((string) $cell), $cells);

            if (implode('', $cells) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = $cells;

                continue;
            }

            if (count($rows) >= $maxRows) {
                fclose($handle);

                throw new InvalidArgumentException('This file has more than '.number_format($maxRows).' rows. Please split it into smaller files.');
            }

            $rows[] = $cells;
        }

        fclose($handle);

        if ($headers === null) {
            throw new InvalidArgumentException('The file has no header row.');
        }

        $width = count($headers);
        $headers = array_map(fn ($header, $i) => $header !== '' ? $header : 'Column '.($i + 1), $headers, array_keys($headers));

        $rows = array_map(fn (array $row) => array_slice(array_pad($row, $width, ''), 0, $width), $rows);

        return ['delimiter' => $delimiter, 'headers' => array_values($headers), 'rows' => $rows];
    }

    private static function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }

    /**
     * The separator that splits the header line into the most cells (quotes respected).
     */
    private static function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = count(str_getcsv($firstLine, $candidate, '"', ''));

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
