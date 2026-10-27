// The accounting screen lives at /{club}/admin/accounting/{tab}/{report}. These work out which tab and report an address
// names, and the address a tab and report belong at, so the page and the tests agree.

export const VALID_TABS = ['home', 'sales', 'purchases', 'reporting', 'accounting', 'bank-accounts', 'chart-of-accounts', 'reconciliation', 'contacts', 'settings'];
export const VALID_REPORTS = ['account_summary', 'aged_payables', 'aged_receivables', 'balance_sheet', 'cash_summary', 'executive_summary', 'profit_and_loss', 'comparative_income_expenditure', 'reconciliation_summary', 'vat_return', 'fixed_assets', 'budget_vs_actual', 'treasurer_report', 'recurring_bills', 'provincial_due', 'independent_examiner'];
export const EXPORTABLE_REPORTS = ['account_summary', 'aged_payables', 'aged_receivables', 'balance_sheet', 'cash_summary', 'executive_summary', 'profit_and_loss', 'comparative_income_expenditure', 'budget_vs_actual', 'provincial_due'];
// Comparative I&E already has its own PDF via the Annual Treasurer's Report.
export const PDF_EXPORTABLE_REPORTS = ['account_summary', 'aged_payables', 'aged_receivables', 'balance_sheet', 'cash_summary', 'executive_summary', 'profit_and_loss', 'budget_vs_actual', 'provincial_due'];

/**
 * The tab and report named by an address: the path after /{club}/admin/accounting first, then a #tab/report hash, then
 * the page's own initial values. `location` is `{ pathname, hash }`; `initial` is `{ tab, report }` from the server.
 */
export function accountingStateFromLocation(location, clubSlug, initial = {}) {
    const initialTab = initial.tab || 'home';
    const initialReport = initial.report || null;
    const basePath = `/${clubSlug}/admin/accounting`;

    if (location.pathname.startsWith(basePath)) {
        const subPath = location.pathname.substring(basePath.length).replace(/^\/+|\/+$/g, '');
        if (subPath) {
            const parts = subPath.split('/');
            const tab = VALID_TABS.includes(parts[0]) ? parts[0] : initialTab;
            const report = (parts[1] && VALID_REPORTS.includes(parts[1])) ? parts[1] : (tab === 'reporting' ? initial.report : null);
            return { tab, report: report ?? null };
        }
    }

    const hash = (location.hash || '').replace('#', '').trim();
    if (hash) {
        const parts = hash.split('/');
        const tab = VALID_TABS.includes(parts[0]) ? parts[0] : 'home';
        const report = (parts[1] && VALID_REPORTS.includes(parts[1])) ? parts[1] : null;
        return { tab, report };
    }

    return { tab: initialTab, report: initialReport };
}

/** The address a tab (and, on the reports tab, a report) lives at. */
export function accountingPath(clubSlug, tab, report = null) {
    let path = `/${clubSlug}/admin/accounting`;

    if (tab && tab !== 'home') {
        path += `/${tab}`;
        if (tab === 'reporting' && report) path += `/${report}`;
    }

    return path;
}
