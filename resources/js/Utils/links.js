// Helpers for the link boxes in the website builder. Keep the rule in step with App\Support\BlockNormaliser::link().

/** A link that already opens properly: a page on this site (/...), an anchor (#...), mailto: or tel:, or a web address. */
export const hasLinkStart = (value) => /^(https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i.test(String(value ?? '').trim());

/** Text that is written like a link but with some other scheme (javascript:, data:, ftp:...), which the site never uses. */
export const hasUnsafeScheme = (value) => {
    const text = String(value ?? '').trim();

    // A port ("example.org:8080") is not a scheme.
    return /^[a-z][a-z0-9+.-]*:(?!\d)/i.test(text) && !/^(https?:\/\/|mailto:|tel:)/i.test(text);
};

/**
 * Puts https:// in front of a web address typed without it ("www.ugle.org.uk", "//ugle.org.uk"), so the link opens the
 * other site instead of a page on this one. Returns { url, changed }.
 */
export function completeWebAddress(value) {
    const text = String(value ?? '').trim();

    if (text === '' || hasLinkStart(text) || hasUnsafeScheme(text)) {
        return { url: text, changed: false };
    }

    return { url: `https://${text.replace(/^\/\//, '')}`, changed: true };
}

/** Whether the text is fit to save as an external link: empty, or a web address / mail / phone link with no spaces. */
export const isValidExternalLink = (value) => {
    const text = String(value ?? '').trim();

    return text === '' || (/^(https?:\/\/[^\s/$.?#][^\s]*|mailto:\S+|tel:\S+)$/i.test(text));
};
