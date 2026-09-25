// A date-time is stored as an exact moment (ISO 8601, UTC) but typed and shown in the admin's own timezone, which is what
// <input type="datetime-local"> works in. These two turn one into the other.

const pad = (n) => String(n).padStart(2, '0');

/** "2027-01-10T09:00" in the browser's timezone for an ISO string (or ''), as a datetime-local input wants it. */
export function toLocalInput(iso) {
    if (!iso) return '';

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) return '';

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** An ISO string (UTC) for what was typed into a datetime-local input; null when empty or not a date. */
export function fromLocalInput(text) {
    if (!text) return null;

    const date = new Date(text);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

/** "12 Apr 2027, 09:00" for an ISO string, in the browser's timezone. */
export function formatLocal(iso) {
    if (!iso) return '';

    return new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
