<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans email wording written by a lodge. It keeps the formatting an email needs (headings, paragraphs, lists,
 * links, inline styles) and drops scripts, event handlers and unsafe link types. A `{{placeholder}}` used as a
 * link address is kept, because it is filled in with a real address later.
 */
class EmailTemplateSanitizer
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(string $html): string
    {
        $swapped = preg_replace('/href\s*=\s*(["\'])\s*\{\{\s*(\w+)\s*\}\}\s*\1/i', 'href="https://placeholder.invalid/$2"', $html) ?? $html;
        $clean = self::sanitizer()->sanitize($swapped);

        return preg_replace('#https://placeholder\.invalid/(\w+)#', '{{$1}}', $clean) ?? $clean;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = new HtmlSanitizerConfig;

        foreach (['h1', 'h2', 'h3', 'h4', 'p', 'span', 'div', 'strong', 'b', 'em', 'i', 'u', 'small', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tr'] as $element) {
            $config = $config->allowElement($element, ['style', 'class']);
        }

        $config = $config
            ->allowElement('br')
            ->allowElement('hr')
            ->allowElement('th', ['style', 'colspan', 'rowspan'])
            ->allowElement('td', ['style', 'colspan', 'rowspan'])
            ->allowElement('a', ['href', 'title', 'style'])
            ->allowElement('img', ['src', 'alt', 'width', 'height', 'style'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowMediaSchemes(['https', 'http'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(100_000);

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
