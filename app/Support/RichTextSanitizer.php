<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises rich text produced by the TipTap editor.
 *
 * This content is rendered with v-html, so anything that survives here runs in
 * the browser of every member who reads it. The allow-list below covers what
 * the editor can actually produce; everything else is dropped.
 */
class RichTextSanitizer
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return self::onlyHarmlessStyles(self::sanitizer()->sanitize($html));
    }

    /**
     * Inline styles are kept (the weekly digest and older content use them) but only for looks: no `position`,
     * `z-index` or `url()`, so nothing a writer saves can cover the page with a fake sign-in box.
     */
    private static function onlyHarmlessStyles(string $html): string
    {
        $property = '/^(color|background(-color)?|font(-size|-weight|-style|-family)?|line-height|text-(align|decoration|transform)|letter-spacing|margin(-(top|right|bottom|left))?|padding(-(top|right|bottom|left))?|border(-(top|right|bottom|left))?(-(width|style|color))?|border-radius|width|max-width|min-width|height|display|box-shadow|vertical-align|white-space|list-style-type)$/';

        return preg_replace_callback('/ style="([^"]*)"/i', function (array $found) use ($property): string {
            $kept = [];

            foreach (explode(';', html_entity_decode($found[1], ENT_QUOTES)) as $declaration) {
                [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
                $name = strtolower(trim($name));
                $value = trim($value);

                if (preg_match($property, $name) && $value !== '' && preg_match('/^[\w\s#.,%()\-+\/!\'"]*$/', $value) && ! preg_match('/url\s*\(|expression|javascript/i', $value)) {
                    $kept[] = "{$name}: {$value}";
                }
            }

            return $kept === [] ? '' : ' style="'.htmlspecialchars(implode('; ', $kept), ENT_QUOTES).'"';
        }, $html) ?? $html;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowStaticElements()
            ->allowElement('p', ['class', 'style'])
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('blockquote')
            ->allowElement('h1', ['class'])
            ->allowElement('h2', ['class'])
            ->allowElement('h3', ['class'])
            ->allowElement('h4', ['class'])
            ->allowElement('h5', ['class'])
            ->allowElement('h6', ['class'])
            ->allowElement('ul', ['class'])
            ->allowElement('ol', ['class'])
            ->allowElement('li', ['class'])
            ->allowElement('hr')
            ->allowElement('span', ['class', 'style'])
            ->allowElement('div', ['class', 'style'])
            ->allowElement('table', ['class'])
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            // Forms would let a writer plant a fake sign-in box that posts to another site.
            ->dropElement('form')
            ->dropElement('input')
            ->dropElement('button')
            ->dropElement('select')
            ->dropElement('textarea')
            ->dropElement('option')
            ->dropElement('optgroup')
            ->dropElement('fieldset')
            ->dropElement('datalist')
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowMediaSchemes(['https', 'http', 'data'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(2_000_000);

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
