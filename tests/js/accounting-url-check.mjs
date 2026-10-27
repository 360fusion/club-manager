// Which tab and report an accounting address names, and which address a tab belongs at.
import { accountingPath, accountingStateFromLocation } from '../../resources/js/Utils/accountingUrl.js';

const at = (pathname, hash = '') => ({ pathname, hash });
const fromServer = { tab: 'reporting', report: 'balance_sheet' };

const states = [
    [at('/lodge/admin/accounting'), {}, { tab: 'home', report: null }],
    [at('/lodge/admin/accounting/sales'), {}, { tab: 'sales', report: null }],
    [at('/lodge/admin/accounting/reporting/vat_return'), {}, { tab: 'reporting', report: 'vat_return' }],
    [at('/lodge/admin/accounting/reporting/nonsense'), fromServer, { tab: 'reporting', report: 'balance_sheet' }],
    [at('/lodge/admin/accounting/nonsense'), { tab: 'contacts' }, { tab: 'contacts', report: null }],
    [at('/lodge/admin/accounting/sales/vat_return'), {}, { tab: 'sales', report: 'vat_return' }],
    [at('/elsewhere', '#purchases'), {}, { tab: 'purchases', report: null }],
    [at('/elsewhere', '#reporting/cash_summary'), {}, { tab: 'reporting', report: 'cash_summary' }],
    [at('/elsewhere', '#nonsense'), {}, { tab: 'home', report: null }],
    [at('/elsewhere'), fromServer, { tab: 'reporting', report: 'balance_sheet' }],
];

const paths = [
    [['lodge', 'home'], '/lodge/admin/accounting'],
    [['lodge', 'sales'], '/lodge/admin/accounting/sales'],
    [['lodge', 'reporting', 'vat_return'], '/lodge/admin/accounting/reporting/vat_return'],
    [['lodge', 'sales', 'vat_return'], '/lodge/admin/accounting/sales'],
    [['lodge', 'reporting'], '/lodge/admin/accounting/reporting'],
];

const problems = [];
states.forEach(([location, initial, want]) => {
    const got = accountingStateFromLocation(location, 'lodge', initial);
    if (JSON.stringify(got) !== JSON.stringify(want)) problems.push(`${location.pathname}${location.hash}: wanted ${JSON.stringify(want)}, got ${JSON.stringify(got)}`);
});
paths.forEach(([args, want]) => {
    const got = accountingPath(...args);
    if (got !== want) problems.push(`path ${args.join(',')}: wanted ${want}, got ${got}`);
});

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
console.log(`ok: ${states.length} addresses and ${paths.length} paths`);
