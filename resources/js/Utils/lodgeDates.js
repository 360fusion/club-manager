const DATE = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

/**
 * The calendar day of an ISO date as a local Date, or null. A full ISO date-time is cut to its date first: date columns
 * arrive as UTC midnight ("2026-10-06T00:00:00.000000Z"), and turning that into local time would give the day before
 * west of London.
 */
function calendarDay(isoDate) {
    const [year, month, day] = String(isoDate ?? '').slice(0, 10).split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return year && month && day && !Number.isNaN(date.getTime()) ? date : null;
}

/**
 * "Tue 6 Oct 2026, 19:00" from an ISO date (a full ISO date-time is cut to its date) and an optional HH:MM time;
 * '' when there is no date.
 */
export function formatMeetingDate(isoDate, time = null) {
    const date = calendarDay(isoDate);

    if (!date) return '';

    const label = DATE.format(date);

    return time ? `${label}, ${time}` : label;
}

/**
 * "6 Oct 2026", or "6 October 2026" with { month: 'long' }, from an ISO date or date-time; '' when there is no date.
 */
export function formatDate(isoDate, { month = 'short' } = {}) {
    const date = calendarDay(isoDate);

    return date ? date.toLocaleDateString('en-GB', { day: 'numeric', month, year: 'numeric' }) : '';
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/**
 * "1st Tuesday, Jan-Jun, Sep-Dec" from a stored pattern.
 */
export function describePattern(schedule) {
    const months = [...schedule.months].sort((a, b) => a - b);
    const runs = [];

    for (const month of months) {
        const last = runs[runs.length - 1];

        if (last && last[1] === month - 1) {
            last[1] = month;
        } else {
            runs.push([month, month]);
        }
    }

    const label = months.length === 12
        ? 'every month'
        : runs.map(([from, to]) => (from === to ? MONTHS[from - 1] : `${MONTHS[from - 1]}-${MONTHS[to - 1]}`)).join(', ');

    return `${schedule.occurrence} ${schedule.day_of_week}, ${label}`;
}
