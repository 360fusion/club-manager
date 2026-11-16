<?php

namespace Launchpad\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * A translated sentence that has markup in it (a link, emphasised data), as one whole sentence a translator can reorder:
 *
 *     {{ Trans::html('I agree to the :terms.', ['terms' => new HtmlString('<a href="/terms">'.e(__('Terms')).'</a>')]) }}
 *
 * The translated text is escaped, then each :placeholder is replaced: an Htmlable (HtmlString) is put in as given, so the caller escapes
 * what is in it, and any other value is escaped first. The result is an HtmlString, which Blade's {{ }} prints as it is: no {!! !!}.
 */
class Trans
{
    /** @param  array<string, mixed>  $replace */
    public static function html(string $key, array $replace = []): HtmlString
    {
        $pairs = [];

        foreach ($replace as $name => $value) {
            if ($value instanceof Htmlable) {
                $pairs[':'.$name] = $value->toHtml();

                continue;
            }

            // :Name and :NAME as Laravel's own replacements do (the plain name last, so it wins when the name is already capitalised);
            // the case is changed before escaping, or "&lt;" would become "&LT;"
            $pairs[':'.ucfirst($name)] = e(ucfirst((string) $value));
            $pairs[':'.mb_strtoupper($name)] = e(mb_strtoupper((string) $value));
            $pairs[':'.$name] = e((string) $value);
        }

        // strtr takes the longest name first (":email_address" before ":email") and never searches what it put in
        return new HtmlString(strtr(e(__($key)), $pairs));
    }
}
