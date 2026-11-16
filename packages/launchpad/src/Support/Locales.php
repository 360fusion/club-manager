<?php

namespace Launchpad\Support;

use Illuminate\Support\Carbon;

/**
 * The languages the site is offered in (config launchpad.locales, code => name), and which one suits a request.
 */
class Locales
{
    /** @return array<string, string> */
    public static function supported(): array
    {
        return (array) config('launchpad.locales', []);
    }

    public static function default(): string
    {
        return (string) config('app.locale');
    }

    /**
     * The sentences of the current language for the Vue pages (English sentence => translation, the package's lines under the app's). A line equal to its
     * own key is left out, as $t() shows the key anyway: so English, which has no real translations, shares none. (Not compared with default():
     * setLocale() has moved that by the time a page is drawn.)
     *
     * @return array<string, string>
     */
    public static function translations(): array
    {
        $lines = (array) app('translator')->getLoader()->load(app()->getLocale(), '*', '*');

        return array_filter($lines, fn ($line, $key) => is_string($line) && $line !== $key, ARRAY_FILTER_USE_BOTH);
    }

    public static function use(string $locale): void
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    /** The listed language an Accept-Language header likes best (by q-value, then order), or null when none is listed */
    public static function fromHeader(?string $header): ?string
    {
        // keyed as a browser writes tags: lower case, '-' (so a listed pt_BR matches pt-BR)
        $listed = [];
        foreach (array_keys(self::supported()) as $code) {
            $listed[str_replace('_', '-', strtolower($code))] = $code;
        }
        $best = null;
        $bestQ = 0.0;

        // a header is untrusted input: read no more than the first 1,000 characters
        foreach (explode(',', substr((string) $header, 0, 1000)) as $range) {
            [$tag, $params] = array_pad(explode(';', $range, 2), 2, '');
            $tag = str_replace('_', '-', strtolower(trim($tag)));

            // a q that is there but unreadable (q=-1, q=, a cut-off tail) drops the entry rather than ranking it first
            if (trim($params) !== '' && ! preg_match('/^\s*q\s*=\s*([0-9]+(?:\.[0-9]*)?)\s*$/i', $params, $m)) {
                continue;
            }
            $q = trim($params) === '' ? 1.0 : min(1.0, (float) $m[1]);

            $code = $listed[$tag] ?? $listed[strtok($tag, '-')] ?? null;
            if ($code !== null && $q > $bestQ) {
                [$best, $bestQ] = [$code, $q];
            }
        }

        return $best;
    }
}
