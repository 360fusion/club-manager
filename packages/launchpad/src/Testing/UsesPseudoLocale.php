<?php

namespace Launchpad\Testing;

use Illuminate\Support\Facades\Lang;
use Launchpad\Support\Locales;

/**
 * Use in a feature test (the app's too): `use UsesPseudoLocale;`, call `$this->usePseudoLocale()`, render a page, then
 * `$this->assertEveryVisibleStringIsTranslated($response->getContent(), ['Acme'])`.
 *
 * In the pseudo-locale every string that goes through __() or trans_choice() comes out as ⟦the key⟧, so any English left bare was never wrapped.
 * The assertion reads the page's text and the text in these attributes: placeholder, title, alt, aria-label, aria-description, and the value of
 * a submit or button input. It does not read an Inertia/Vue page's data-page JSON (not visible text): `php artisan launchpad:lang:missing xx --vue`
 * is what covers the Vue pages.
 * Laravel's test case calls tearDownUsesPseudoLocale() after each test by itself; where it does not, call tearDownPseudoLocale() from tearDown().
 */
trait UsesPseudoLocale
{
    private ?string $localeBeforePseudo = null;

    protected function usePseudoLocale(): void
    {
        $this->localeBeforePseudo ??= app()->getLocale();
        Locales::use('xx');
        Lang::handleMissingKeysUsing(fn ($key) => $this->pseudoLine($key));
    }

    /**
     * ⟦key⟧; a counted key (`{0} none|{1} one|[2,*] many`) is bracketed per segment, since trans_choice() picks one segment out of whatever
     * this returns and a bracket around the whole would be cut in two.
     */
    private function pseudoLine(string $key): string
    {
        if (! str_contains($key, '|')) {
            return '⟦'.$key.'⟧';
        }

        return implode('|', array_map(
            fn (string $segment) => preg_replace('/^(\s*(?:\{[^}]*\}|[\[(][^\]()]*[\])])?\s*)(.*)$/su', '$1⟦$2⟧', $segment),
            explode('|', $key),
        ));
    }

    protected function tearDownPseudoLocale(): void
    {
        Lang::handleMissingKeysUsing(null);
        if ($this->localeBeforePseudo !== null) {
            Locales::use($this->localeBeforePseudo);
            $this->localeBeforePseudo = null;
        }
    }

    /** Laravel's test case calls tearDown<TraitName>() on its own */
    protected function tearDownUsesPseudoLocale(): void
    {
        $this->tearDownPseudoLocale();
    }

    /**
     * Fails for any run of 3 or more letters left in the page's visible text once script, style, comments, tags and ⟦…⟧ are gone.
     *
     * @param  list<string>  $allow  words (or phrases) that need no translation, such as a product name; matched whole and ignoring case
     */
    protected function assertEveryVisibleStringIsTranslated(string $html, array $allow = []): void
    {
        $html = preg_replace(['#<(script|style)\b.*?</\1>#is', '/<!--.*?-->/s'], ' ', $html);
        $text = preg_replace('/<[^>]*>/', ' ', $this->withoutBrackets($html)).' '.implode(' ', $this->textInAttributes($html));
        // an attribute's value is bare or wrapped like any other text
        $text = $this->withoutBrackets($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach ($allow as $entry) {
            $text = preg_replace('/(?<!\p{L})'.preg_quote($entry, '/').'(?!\p{L})/iu', ' ', $text);
        }

        preg_match_all('/\p{L}{3,}/u', $text, $runs);

        $bare = array_values(array_unique($runs[0]));

        $this->assertTrue($bare === [], "These words show on the page without going through __() (wrap them, or allow them):\n".implode(', ', $bare));
    }

    /** Removes ⟦…⟧ from the inside out: a sentence with a translated link label in it (Trans::html) holds a pair of its own */
    private function withoutBrackets(string $text): string
    {
        do {
            $text = preg_replace('/⟦[^⟦⟧]*⟧/su', ' ', $text, -1, $count);
        } while ($count > 0);

        return $text;
    }

    /** @return list<string> */
    private function textInAttributes(string $html): array
    {
        preg_match_all('/<[a-z][^>]*>/i', $html, $tags);

        $values = [];
        foreach ($tags[0] as $tag) {
            $names = 'placeholder|title|alt|aria-label|aria-description';
            // only a button's value is shown; a text field's is what the person typed
            if (preg_match('/<input\b/i', $tag) && preg_match('/\btype\s*=\s*["\']?(submit|button)\b/i', $tag)) {
                $names .= '|value';
            }
            preg_match_all('/(?<![\w-])(?:'.$names.')\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $found, PREG_SET_ORDER);
            foreach ($found as $match) {
                $values[] = $match[2] ?? $match[1];
            }
        }

        return $values;
    }
}
