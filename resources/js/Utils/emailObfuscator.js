/**
 * Utility functions for obfuscating email addresses on public web pages to prevent spam harvesting bots.
 */

// Base64 Encode Email
export function encodeEmail(email) {
    if (!email) return '';
    try {
        return btoa(email);
    } catch (e) {
        return email;
    }
}

// Base64 Decode Email
export function decodeEmail(encoded) {
    if (!encoded) return '';
    try {
        return atob(encoded);
    } catch (e) {
        return encoded;
    }
}

/**
 * Parses raw HTML strings and replaces plain text email addresses with bot-safe obfuscated spans.
 */
export function obfuscateEmailsInHtml(html) {
    if (!html || typeof html !== 'string') return html;

    // Matches standard email format strings
    const emailRegex = /([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g;

    return html.replace(emailRegex, (match) => {
        const encoded = encodeEmail(match);
        const safeDisplay = match.replace('@', ' [at] ');
        return `<span class="obfuscated-email" data-b64="${encoded}" onclick="window.location.href='mailto:'+atob('${encoded}')" style="cursor:pointer;" title="Click to send email">${safeDisplay}</span>`;
    });
}
