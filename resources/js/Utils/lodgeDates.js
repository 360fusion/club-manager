const DATE = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

/**
 * "Tue 6 Oct 2026, 19:00" from an ISO date and an optional HH:MM time.
 */
export function formatMeetingDate(isoDate, time = null) {
    const [year, month, day] = isoDate.split('-').map(Number);
    const label = DATE.format(new Date(year, month - 1, day));

    return time ? `${label}, ${time}` : label;
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
