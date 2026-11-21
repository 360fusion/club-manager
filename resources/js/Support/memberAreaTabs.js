// The tabs across the lodge admin's member pages, for the Vue ones (officers, sign-ins and access). The Livewire pages
// have the same list in resources/views/livewire/members/_tabs.blade.php; those need a full page load (reload: true).
export function memberAreaTabs(clubSlug) {
    return [
        { key: 'members', label: 'Members roster', href: route('admin.club_acc.members.index', { clubSlug }), reload: true },
        { key: 'officers', label: 'Annual officer rosters & history', href: route('admin.officers.index', { clubSlug }) },
        { key: 'candidates', label: 'Candidates (Form P vetting)', href: route('admin.club_acc.candidates.index', { clubSlug }), reload: true },
        { key: 'subscriptions', label: 'Subscriptions & dues', href: route('admin.club_acc.subscriptions.index', { clubSlug }), reload: true },
        { key: 'access', label: 'Sign-ins and access', href: route('admin.users.index', { clubSlug }) },
    ];
}
