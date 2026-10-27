// The website builder's page settings rules: what counts as a default page, the settings a page starts from, the footer
// link columns a reloaded page gets back, and how a page's life (scheduled, expired, coming down) is described.
import { isDefaultPage, lifecycleText, normalizeFooterLinkColumns, settingsStateOf } from '../../resources/js/Utils/pageBuilderSettings.js';

const problems = [];
const same = (label, got, want) => { if (JSON.stringify(got) !== JSON.stringify(want)) problems.push(`${label}: wanted ${JSON.stringify(want)}, got ${JSON.stringify(got)}`); };

same('home is a default page', isDefaultPage({ slug: 'anything', is_homepage: true }), true);
same('about is a default page', isDefaultPage({ slug: 'about' }), true);
same('a custom page is not', isDefaultPage({ slug: 'my-page', is_homepage: false }), false);
same('no page is not', isDefaultPage(null), false);

same('a bare page starts published, in the menu, not in the footer', settingsStateOf({}), {
    slug: '', is_published: true, is_homepage: false, show_in_navigation: true, show_in_footer: false, is_members_only: false,
    header_style: 'full', publish_at: null, unpublish_at: null,
});
same('dates become ISO strings', settingsStateOf({ slug: 'x', publish_at: '2027-01-10T09:00:00Z' }).publish_at, '2027-01-10T09:00:00.000Z');
same('false is kept, not defaulted', settingsStateOf({ is_published: false, show_in_navigation: false }).is_published, false);

const columns = normalizeFooterLinkColumns([{ title: 'Links', links: [{ label: 'Home', url: '/' }, { id: 'keep', label: 'About' }] }, {}]);
same('columns all get ids', columns.every((c) => c.id && c.links.every((l) => l.id)), true);
same('an existing id is kept', columns[0].links[1].id, 'keep');
same('missing parts become empty text', columns[1], { id: columns[1].id, title: '', links: [] });
same('no columns', normalizeFooterLinkColumns(null), []);

same('no page', lifecycleText(null), '');
same('live with nothing scheduled', lifecycleText({ lifecycle_status: 'live', is_homepage: false }), '');
same('the homepage never comes down', lifecycleText({ lifecycle_status: 'live', unpublish_at: '2027-01-10T09:00:00Z', is_homepage: true }), '');
same('scheduled mentions going live', /^Goes live /.test(lifecycleText({ lifecycle_status: 'scheduled', publish_at: '2027-01-10T09:00:00Z' })), true);
same('expired says it came down', /^Came down /.test(lifecycleText({ lifecycle_status: 'expired', unpublish_at: '2026-01-10T09:00:00Z' })), true);
same('a live page with an end date says when it comes down', /^Comes down /.test(lifecycleText({ lifecycle_status: 'live', unpublish_at: '2027-01-10T09:00:00Z', is_homepage: false })), true);

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
console.log('ok: website builder page settings');
