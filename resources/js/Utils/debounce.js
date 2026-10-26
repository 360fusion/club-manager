/**
 * `fn`, run only once calls have stopped for `wait` ms (a search box that reloads the list as you type).
 * `.cancel()` drops a pending call.
 */
export function debounce(fn, wait = 300) {
    let timer;

    const debounced = (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
    debounced.cancel = () => clearTimeout(timer);

    return debounced;
}
