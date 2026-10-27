// The website builder's addresses: a screen or page maps to an address and back, and addresses that are not the
// builder's own are refused.
import { getPageIcon, parseSelectionUrl, selectionUrl } from '../../resources/js/Utils/pageBuilderUrl.js';

const problems = [];
const same = (label, got, want) => { if (JSON.stringify(got) !== JSON.stringify(want)) problems.push(`${label}: wanted ${JSON.stringify(want)}, got ${JSON.stringify(got)}`); };

same('settings address', selectionUrl('lodge', 'settings'), '/lodge/admin/pages/settings');
same('header and footer address', selectionUrl('lodge', 'header_footer'), '/lodge/admin/pages/header-footer');
same('new page address', selectionUrl('lodge', 'new', 'edit'), '/lodge/admin/pages/create');
same('page edit address', selectionUrl('lodge', 7), '/lodge/admin/pages/7/edit');
same('page preview address', selectionUrl('lodge', '7', 'preview'), '/lodge/admin/pages/7/edit?view=preview');

same('parse settings', parseSelectionUrl('/lodge/admin/pages/settings'), { selection: 'settings', mode: 'edit' });
same('parse create', parseSelectionUrl('/lodge/admin/pages/create'), { selection: 'new', mode: 'edit' });
same('parse page', parseSelectionUrl('/lodge/admin/pages/7/edit'), { selection: '7', mode: 'edit' });
same('parse preview', parseSelectionUrl('/lodge/admin/pages/7/edit?view=preview'), { selection: '7', mode: 'preview' });
same('parse unknown view', parseSelectionUrl('/lodge/admin/pages/7/edit?view=zzz'), { selection: '7', mode: 'edit' });
same('parse a page id without /edit', parseSelectionUrl('/lodge/admin/pages/7'), null);
same('parse other admin page', parseSelectionUrl('/lodge/admin/media-manager'), null);
same('parse revisions', parseSelectionUrl('/lodge/admin/pages/7/revisions'), null);

for (const selection of ['settings', 'themes', 'header_footer', 'redirects', 'overview', 'new']) {
    same(`round trip ${selection}`, parseSelectionUrl(selectionUrl('lodge', selection)), { selection, mode: 'edit' });
}

same('home icon', getPageIcon({ slug: 'x', is_homepage: true }), '🏠');
same('about icon', getPageIcon({ slug: 'about' }), 'ℹ️');
same('other icon', getPageIcon({ slug: 'my-page' }), '📄');
same('no page icon', getPageIcon(null), '📄');

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
console.log('ok: website builder addresses and page icons');
