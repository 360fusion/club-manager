import { localDate } from './localDateTime.js';

/** Where the "all time" range starts. */
export const ALL_TIME_START = '1970-01-01';

/** The preset ranges offered on the reports tab. */
export const REPORT_RANGE_PRESETS = ['this_month', 'last_month', 'this_year', 'all_time'];

/**
 * The first and last day (as "YYYY-MM-DD" in the viewer's own timezone) of a preset range, or null for 'custom' or
 * anything else. `today` is a Date, so the result can be checked for any day.
 */
export function reportDateRange(preset, today = new Date()) {
    const y = today.getFullYear();
    const m = today.getMonth();

    switch (preset) {
        case 'this_month':
            return { start: localDate(new Date(y, m, 1)), end: localDate(new Date(y, m + 1, 0)) };
        case 'last_month':
            return { start: localDate(new Date(y, m - 1, 1)), end: localDate(new Date(y, m, 0)) };
        case 'this_year':
            return { start: localDate(new Date(y, 0, 1)), end: localDate(new Date(y, 11, 31)) };
        case 'all_time':
            // "All time" must reach back past any date a ledger entry or opening balance can be given, so an older
            // entry is never silently left out of the report.
            return { start: ALL_TIME_START, end: localDate(today) };
        default:
            return null;
    }
}
