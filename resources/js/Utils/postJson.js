// Posts JSON with the session and CSRF token Laravel expects. Returns { ok, status, data } and never throws on HTTP errors.
export async function postJson(url, body) {
    const xsrf = decodeURIComponent((document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN=')) || '').split('=')[1] || '');

    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify(body),
        });

        return { ok: response.ok, status: response.status, data: await response.json().catch(() => ({})) };
    } catch {
        return { ok: false, status: 0, data: { message: 'Could not work out the price. Check your connection.' } };
    }
}
