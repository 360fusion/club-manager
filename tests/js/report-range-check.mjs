// The reports tab's preset date ranges, including the edges that are easy to get wrong: January's "last month" is
// last year's December, February in a leap year, and a month with 31 days.
import { reportDateRange } from '../../resources/js/Utils/reportDateRange.js';

const cases = [
    ['this_month', new Date(2026, 9, 27), { start: '2026-10-01', end: '2026-10-31' }],
    ['this_month', new Date(2028, 1, 10), { start: '2028-02-01', end: '2028-02-29' }],
    ['last_month', new Date(2026, 0, 15), { start: '2025-12-01', end: '2025-12-31' }],
    ['last_month', new Date(2026, 2, 31), { start: '2026-02-01', end: '2026-02-28' }],
    ['this_year', new Date(2026, 5, 5), { start: '2026-01-01', end: '2026-12-31' }],
    ['all_time', new Date(2026, 9, 27), { start: '1970-01-01', end: '2026-10-27' }],
    ['custom', new Date(2026, 9, 27), null],
];

const failures = cases.filter(([preset, today, want]) => JSON.stringify(reportDateRange(preset, today)) !== JSON.stringify(want));

if (failures.length) {
    failures.forEach(([preset, today, want]) => console.error(`${preset} on ${today.toDateString()}: wanted ${JSON.stringify(want)}, got ${JSON.stringify(reportDateRange(preset, today))}`));
    process.exit(1);
}
console.log(`ok: ${cases.length} report date ranges`);
