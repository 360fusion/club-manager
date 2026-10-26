{{-- The member area's sections, shared by the Livewire pages; the officers page (Vue) has the same list in RosterIndex.vue. --}}
<x-tabs label="Members" :active="$active" :items="[
    ['key' => 'members', 'label' => 'Members roster', 'href' => route('admin.club_acc.members.index', ['clubSlug' => $club->slug])],
    ['key' => 'officers', 'label' => 'Annual officer rosters & history', 'href' => route('admin.officers.index', ['clubSlug' => $club->slug])],
    ['key' => 'candidates', 'label' => 'Candidates (Form P vetting)', 'href' => route('admin.club_acc.candidates.index', ['clubSlug' => $club->slug])],
    ['key' => 'subscriptions', 'label' => 'Subscriptions & dues', 'href' => route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug])],
]" />
