/**
 * The label of the financial year a date falls in, from the month the club's year ends (1-12): "2025-26" for a year
 * that runs May 2025 to April 2026, or just "2026" when the year ends in December. Null for an empty or unreadable date.
 * `endMonth` is the club's own setting, so a club whose year ends in April is not labelled as if it ended in August.
 */
export function financialYearLabel(dateStr, endMonth = 4) {
    if (!dateStr) return null;

    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return null;

    const end = Number.isInteger(Number(endMonth)) && endMonth >= 1 && endMonth <= 12 ? Number(endMonth) : 4;
    const startMonth = (end % 12) + 1;
    const month = date.getMonth() + 1;
    const year = date.getFullYear();

    if (startMonth === 1) return String(year);

    const startYear = month >= startMonth ? year : year - 1;

    return `${startYear}-${String(startYear + 1).slice(2)}`;
}
