import { ref } from 'vue';

/**
 * Copy text to the clipboard. `copied` holds what was just copied for a moment, so a button can say "Copied" (compare it
 * with the value when a page copies several things); `copy()` resolves false when the browser refused, so the page can
 * ask people to select the text themselves. Where the Clipboard API is missing (a site on plain http) it falls back to
 * copying from a hidden text box.
 */
export function useClipboard({ resetAfter = 2000 } = {}) {
    const copied = ref('');
    let timer;

    const copy = async (text) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else if (!copyFromTextBox(text)) {
                return false;
            }
        } catch {
            return false;
        }

        copied.value = text;
        clearTimeout(timer);
        timer = setTimeout(() => { copied.value = ''; }, resetAfter);

        return true;
    };

    return { copied, copy };
}

function copyFromTextBox(text) {
    const box = document.createElement('textarea');
    box.value = text;
    box.setAttribute('readonly', '');
    box.style.position = 'fixed';
    box.style.left = '-9999px';
    document.body.appendChild(box);
    box.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(box);
    }
}
