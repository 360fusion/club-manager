// Which financial year a date falls in, for clubs whose year ends in different months.
import { financialYearLabel } from '../../resources/js/Utils/financialYear.js';

const cases = [
    // [date, end month, label]
    ['12 Sep 2026', 4, '2026-27'], // a year ending in April starts in May...
    ['30 Apr 2026', 4, '2025-26'], // ...so the last day of April still belongs to the old year
    ['1 May 2026', 4, '2026-27'],
    ['15 Jan 2027', 4, '2026-27'],
    ['12 Sep 2026', 8, '2026-27'], // a year ending in August starts in September
    ['31 Aug 2026', 8, '2025-26'],
    ['1 Sep 2026', 8, '2026-27'],
    ['1 Jan 2026', 12, '2026'], // a calendar year is just the year
    ['31 Dec 2026', 12, '2026'],
    ['31 Mar 2026', 3, '2025-26'], // a year ending in March
    ['1 Apr 2026', 3, '2026-27'],
    ['', 4, null],
    [null, 4, null],
    ['not a date', 4, null],
    ['12 Sep 2026', 'junk', '2026-27'], // an unusable setting falls back to April
];

const failures = cases.filter(([date, end, want]) => financialYearLabel(date, end) !== want);

if (failures.length) {
    failures.forEach(([date, end, want]) => console.error(`${date} (year ends month ${end}): wanted ${want}, got ${financialYearLabel(date, end)}`));
    process.exit(1);
}
console.log(`ok: ${cases.length} financial year labels`);
