/**
 * `v-focus-trap="onClose"` — for a modal/dialog root that is only ever in the DOM while it's open
 * (i.e. behind `v-if`, not `v-show`). On mount it remembers whatever had focus, moves focus inside
 * the dialog, keeps Tab from leaving it, and restores focus to the trigger on unmount. Escape calls
 * the bound handler, if one is given, so the caller decides what "close" means.
 *
 * Usage: <div role="dialog" aria-modal="true" aria-labelledby="my-title" v-focus-trap="() => show = false">
 */

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

const focusableWithin = (el) => Array.from(el.querySelectorAll(FOCUSABLE_SELECTOR))
    .filter((node) => node.offsetParent !== null || node === document.activeElement);

const handleKeydown = (el, onClose) => (event) => {
    if (event.key === 'Escape' && typeof onClose === 'function') {
        event.stopPropagation();
        onClose();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusable = focusableWithin(el);
    if (focusable.length === 0) {
        event.preventDefault();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

export default {
    mounted(el, binding) {
        el.__focusTrap = {
            previouslyFocused: document.activeElement,
            listener: handleKeydown(el, binding.value),
        };

        if (!el.hasAttribute('tabindex')) {
            el.setAttribute('tabindex', '-1');
        }

        document.addEventListener('keydown', el.__focusTrap.listener, true);

        // Vue has already patched this element's whole subtree into the DOM by the time `mounted`
        // fires, so focus can move in synchronously — no need for requestAnimationFrame, which
        // browsers throttle or never fire at all while the document isn't the visible/active tab.
        const [firstFocusable] = focusableWithin(el);
        (firstFocusable || el).focus();
    },
    unmounted(el) {
        if (!el.__focusTrap) {
            return;
        }

        document.removeEventListener('keydown', el.__focusTrap.listener, true);

        const { previouslyFocused } = el.__focusTrap;
        if (previouslyFocused && document.body.contains(previouslyFocused)) {
            previouslyFocused.focus();
        }

        delete el.__focusTrap;
    },
};
