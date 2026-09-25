<?php

namespace App\Support;

/**
 * Reads a YouTube link and returns its video id (and start time, when the link has one).
 *
 * Anything that is not a recognisable YouTube video link returns null, so only a validated
 * 11-character id ever reaches an embed. resources/js/Utils/youtube.js mirrors this parser.
 */
class YouTubeUrl
{
    private const HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtube-nocookie.com', 'www.youtube-nocookie.com',
        'youtu.be', 'www.youtu.be',
    ];

    /**
     * @return array{id: string, start: int|null}|null
     */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Allow a link pasted without its scheme, e.g. "youtu.be/abc" or "www.youtube.com/watch?v=abc".
        if (preg_match('#^(www\.|m\.|music\.)?(youtube(-nocookie)?\.com|youtu\.be)/#i', $url) === 1) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (! in_array($host, self::HOSTS, true)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), fn ($segment) => $segment !== ''));
        parse_str($parts['query'] ?? '', $query);

        if (str_ends_with($host, 'youtu.be')) {
            $id = $segments[0] ?? '';
        } elseif (($segments[0] ?? '') === 'watch') {
            $id = is_string($query['v'] ?? null) ? $query['v'] : '';
        } elseif (in_array($segments[0] ?? '', ['embed', 'shorts', 'live', 'v'], true)) {
            $id = $segments[1] ?? '';
        } else {
            return null;
        }

        if (! self::isValidId($id)) {
            return null;
        }

        $fragment = [];
        parse_str($parts['fragment'] ?? '', $fragment);

        $start = null;

        foreach ([$query['t'] ?? null, $query['start'] ?? null, $fragment['t'] ?? null] as $candidate) {
            if (is_string($candidate) && ($seconds = self::parseSeconds($candidate)) !== null) {
                $start = $seconds;

                break;
            }
        }

        return ['id' => $id, 'start' => $start];
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1;
    }

    public static function watchUrl(string $id): string
    {
        return 'https://www.youtube.com/watch?v='.$id;
    }

    /**
     * Seconds from "90", "90s", "1m30s" or "1h2m3s"; null when it is not a time.
     */
    public static function parseSeconds(string $value): ?int
    {
        $value = strtolower(trim($value));

        if (preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        if ($value !== '' && preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $m) === 1) {
            return ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);
        }

        return null;
    }
}
