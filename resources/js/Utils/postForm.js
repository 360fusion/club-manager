// Posts multipart form data (a file upload) with the session and CSRF token Laravel expects.
// Returns { ok, status, data } and never throws on HTTP errors.
export async function postForm(url, formData) {
    const xsrf = decodeURIComponent((document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN=')) || '').split('=')[1] || '');

    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
            body: formData,
        });

        const data = await response.json().catch(() => ({}));

        return { ok: response.ok, status: response.status, data };
    } catch {
        return { ok: false, status: 0, data: { message: 'The upload failed. Check your connection.' } };
    }
}
