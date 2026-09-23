<?php

namespace App\Domains\ClubAccounting\Livewire\Members;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Concerns\ShowsNotice;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportFields;
use App\Domains\ClubAccounting\Services\MemberInvitationException;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Models\Club;
use App\Support\ClubAccess;
use App\Support\Csv;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MemberIndex extends Component
{
    use ShowsNotice;
    use WithPagination;

    #[Locked]
    public string $clubSlug;

    // Filters & Search
    public string $search = '';

    public string $statusFilter = 'active';

    public string $officeFilter = 'all';

    public string $rankFilter = 'all';

    public string $accountFilter = 'all';

    /** @var array<int, int|string> Members ticked for a bulk invitation; only trusted after a club-scoped lookup. */
    public array $selected = [];

    public string $sortField = 'last_name';

    public string $sortDirection = 'asc';

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOfficeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRankFilter(): void
    {
        $this->resetPage();
    }

    public function updatingAccountFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function inviteMember(int $memberId, MemberInvitationService $invitations): void
    {
        $club = $this->authorizedClub();
        $member = Member::where('club_id', $club->id)->findOrFail($memberId);

        try {
            $sent = $invitations->invite($member, $club, auth()->user());
            $this->notify($sent['emailed'] ? "Invitation emailed to {$member->email}." : "Email could not be sent. Share this link with {$member->full_name}: {$sent['url']}");
        } catch (MemberInvitationException $e) {
            $this->notify("{$member->full_name}: {$e->getMessage()}.", 'error');
        }
    }

    public function resendInvite(int $memberId, MemberInvitationService $invitations): void
    {
        $club = $this->authorizedClub();
        $member = Member::where('club_id', $club->id)->findOrFail($memberId);

        try {
            $sent = $invitations->resend($member, $club, auth()->user());
            $this->notify($sent['emailed'] ? "Invitation sent again to {$member->email}." : "Invitation renewed. Share this link: {$sent['url']}");
        } catch (MemberInvitationException $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function revokeInvite(int $memberId, MemberInvitationService $invitations): void
    {
        $club = $this->authorizedClub();
        $member = Member::where('club_id', $club->id)->whereNotNull('user_id')->findOrFail($memberId);

        if ($user = $member->user) {
            $invitations->revoke($club, $user);
        }

        $this->notify("Invitation withdrawn for {$member->full_name}.");
    }

    public function inviteSelected(MemberInvitationService $invitations): void
    {
        $club = $this->authorizedClub();
        $ids = $this->selected;
        $this->selected = [];

        $this->flashBulkResult($invitations->inviteMany($club, $ids, auth()->user()));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function deleteMember(int $memberId): void
    {
        $club = $this->getClub();
        $member = Member::where('club_id', $club->id)->findOrFail($memberId);
        $name = $member->full_name;
        $member->delete();

        $this->notify("Member {$name} removed from roster.");
    }

    /**
     * Secretarial Returns & Directory CSV Export
     */
    public function exportCsv()
    {
        $club = $this->getClub();
        $members = Member::where('club_id', $club->id)
            ->search($this->search)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('membership_status', $this->statusFilter))
            ->when($this->officeFilter !== 'all', fn ($q) => $q->where('current_office', $this->officeFilter))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $filename = "Lodge-Roster-{$club->slug}-".now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($members) {
            $file = fopen('php://output', 'w');
            fputcsv($file, array_keys(MemberImportFields::EXPORT_COLUMNS));

            foreach ($members as $m) {
                fputcsv($file, Csv::safe(MemberImportFields::exportRow($m)));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    /**
     * The club, once the user is confirmed as allowed to manage its members. Livewire calls do not pass through the
     * page's route checks again, so every account action re-checks here.
     */
    private function authorizedClub(): Club
    {
        $club = $this->getClub();
        ClubAccess::authorize(auth()->user(), $club, 'manage_members');

        return $club;
    }

    /**
     * @param  array{invited: int, skipped: array<string, int>, failed: int, capped: bool}  $result
     */
    private function flashBulkResult(array $result): void
    {
        $parts = ["Invited {$result['invited']}."];

        foreach ($result['skipped'] as $reason => $count) {
            $parts[] = "Skipped {$count}: {$reason}.";
        }

        if ($result['failed'] > 0) {
            $parts[] = "{$result['failed']} could not be emailed; resend them from the list.";
        }

        if ($result['capped']) {
            $parts[] = 'Only the first '.MemberInvitationService::BULK_LIMIT.' were processed; run it again for the rest.';
        }

        $this->notify(implode(' ', $parts), $result['invited'] > 0 ? 'success' : 'error');
    }

    public function render()
    {
        $club = $this->getClub();

        $query = Member::where('club_id', $club->id)
            ->withAccountState($club->id)
            ->search($this->search)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('membership_status', $this->statusFilter))
            ->when($this->officeFilter !== 'all', fn ($q) => $q->where('current_office', $this->officeFilter))
            ->when($this->rankFilter !== 'all', fn ($q) => $q->where('masonic_rank', $this->rankFilter))
            ->when(in_array($this->accountFilter, ['not_invited', 'invited', 'has_account'], true), fn ($q) => $q->whereAccountState($this->accountFilter, $club->id));

        if ($this->sortField === 'full_name') {
            $query->orderBy('last_name', $this->sortDirection)->orderBy('first_name', $this->sortDirection);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $members = $query->paginate(15);

        $canInvite = ClubAccess::can(auth()->user(), $club, 'manage_members');
        $invitableIds = $canInvite
            ? $members->filter(fn (Member $m) => $m->email && $m->membership_status->isSubscribing() && $m->accountStatus($club)->canInvite())->pluck('id')->all()
            : [];

        // Stats summary
        $totalMembers = Member::where('club_id', $club->id)->count();
        $activeCount = Member::where('club_id', $club->id)->active()->count();
        $officerCount = Member::where('club_id', $club->id)->officers()->count();
        $pmCount = Member::where('club_id', $club->id)->pastMasters()->count();

        return view('livewire.members.member-index', [
            'club' => $club,
            'members' => $members,
            'totalMembers' => $totalMembers,
            'activeCount' => $activeCount,
            'officerCount' => $officerCount,
            'pmCount' => $pmCount,
            'canInvite' => $canInvite,
            'invitableIds' => $invitableIds,
            'ranks' => Member::MASONIC_RANKS,
            'offices' => LodgeOffice::cases(),
            'statuses' => MembershipStatus::cases(),
        ])->layout('components.layouts.app', [
            'title' => 'Lodge Member Directory',
            'club' => $club,
        ]);
    }
}
