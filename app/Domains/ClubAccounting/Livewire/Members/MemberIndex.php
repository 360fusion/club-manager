<?php

namespace App\Domains\ClubAccounting\Livewire\Members;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use Livewire\Component;
use Livewire\WithPagination;

class MemberIndex extends Component
{
    use WithPagination;

    public string $clubSlug;

    // Filters & Search
    public string $search = '';
    public string $statusFilter = 'active';
    public string $officeFilter = 'all';
    public string $rankFilter = 'all';
    public string $sortField = 'last_name';
    public string $sortDirection = 'asc';

    // Add / Edit Member Modal State
    public bool $showMemberModal = false;
    public ?int $editingMemberId = null;

    // Form fields
    public string $title = 'Bro';
    public string $first_name = '';
    public string $middle_names = '';
    public string $last_name = '';
    public string $preferred_name = '';
    public string $email = '';
    public string $phone = '';
    public string $address_line_1 = '';
    public string $address_line_2 = '';
    public string $city = '';
    public string $county = '';
    public string $postcode = '';
    public string $country = '';
    public string $masonic_rank = 'Bro';
    public string $grand_rank = '';
    public string $provincial_rank = '';
    public string $grand_lodge_number = '';
    public string $membership_status = 'active';
    public string $current_office = 'member';
    public ?string $date_of_initiation = null;
    public ?string $date_of_passing = null;
    public ?string $date_of_raising = null;
    public ?string $date_of_joining = null;
    public ?string $annual_dues_override = null;
    public string $notes = '';
    public ?int $customer_account_id = null;

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

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openAddModal(): void
    {
        $this->resetForm();
        $this->showMemberModal = true;
    }

    public function openEditModal(int $memberId): void
    {
        $club = $this->getClub();
        $member = Member::where('club_id', $club->id)->findOrFail($memberId);

        $this->editingMemberId = $member->id;
        $this->title = $member->title ?: 'Bro';
        $this->first_name = $member->first_name;
        $this->middle_names = $member->middle_names ?? '';
        $this->last_name = $member->last_name;
        $this->preferred_name = $member->preferred_name ?? '';
        $this->email = $member->email ?? '';
        $this->phone = $member->phone ?? '';
        $this->address_line_1 = $member->address_line_1 ?? '';
        $this->address_line_2 = $member->address_line_2 ?? '';
        $this->city = $member->city ?? '';
        $this->county = $member->county ?? '';
        $this->postcode = $member->postcode ?? '';
        $this->country = $member->country ?? '';
        $this->masonic_rank = $member->masonic_rank ?? 'Bro';
        $this->grand_rank = $member->grand_rank ?? '';
        $this->provincial_rank = $member->provincial_rank ?? '';
        $this->grand_lodge_number = $member->grand_lodge_number ?? '';
        $this->membership_status = $member->membership_status->value;
        $this->current_office = $member->current_office->value;
        $this->date_of_initiation = $member->date_of_initiation?->format('Y-m-d');
        $this->date_of_passing = $member->date_of_passing?->format('Y-m-d');
        $this->date_of_raising = $member->date_of_raising?->format('Y-m-d');
        $this->date_of_joining = $member->date_of_joining?->format('Y-m-d');
        $this->annual_dues_override = $member->annual_dues_override;
        $this->notes = $member->notes ?? '';
        $this->customer_account_id = $member->customer_account_id;

        $this->showMemberModal = true;
    }

    public function closeModal(): void
    {
        $this->showMemberModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingMemberId = null;
        $this->title = 'Bro';
        $this->first_name = '';
        $this->middle_names = '';
        $this->last_name = '';
        $this->preferred_name = '';
        $this->email = '';
        $this->phone = '';
        $this->address_line_1 = '';
        $this->address_line_2 = '';
        $this->city = '';
        $this->county = '';
        $this->postcode = '';
        $this->country = '';
        $this->masonic_rank = 'Bro';
        $this->grand_rank = '';
        $this->provincial_rank = '';
        $this->grand_lodge_number = '';
        $this->membership_status = 'active';
        $this->current_office = 'member';
        $this->date_of_initiation = null;
        $this->date_of_passing = null;
        $this->date_of_raising = null;
        $this->date_of_joining = null;
        $this->annual_dues_override = null;
        $this->notes = '';
        $this->customer_account_id = null;
        $this->resetValidation();
    }

    public function saveMember(): void
    {
        $this->validate([
            'first_name' => 'required|string|max:100',
            'middle_names' => 'nullable|string|max:150',
            'last_name' => 'required|string|max:100',
            'preferred_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'masonic_rank' => 'required|string|max:50',
            'membership_status' => 'required|string',
            'current_office' => 'required|string',
            'grand_lodge_number' => 'nullable|string|max:50',
            'date_of_initiation' => 'nullable|date',
            'date_of_joining' => 'nullable|date',
        ]);

        $club = $this->getClub();

        $data = [
            'club_id' => $club->id,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'middle_names' => $this->middle_names ?: null,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address_line_1' => $this->address_line_1 ?: null,
            'address_line_2' => $this->address_line_2 ?: null,
            'city' => $this->city ?: null,
            'county' => $this->county ?: null,
            'postcode' => $this->postcode ?: null,
            'country' => $this->country ?: null,
            'masonic_rank' => $this->masonic_rank,
            'grand_rank' => $this->grand_rank ?: null,
            'provincial_rank' => $this->provincial_rank ?: null,
            'grand_lodge_number' => $this->grand_lodge_number ?: null,
            'membership_status' => MembershipStatus::from($this->membership_status),
            'current_office' => LodgeOffice::from($this->current_office),
            'date_of_initiation' => $this->date_of_initiation ?: null,
            'date_of_passing' => $this->date_of_passing ?: null,
            'date_of_raising' => $this->date_of_raising ?: null,
            'date_of_joining' => $this->date_of_joining ?: null,
            'annual_dues_override' => $this->annual_dues_override ? (float) $this->annual_dues_override : null,
            'notes' => $this->notes ?: null,
            'customer_account_id' => $this->customer_account_id ?: null,
        ];

        if ($this->editingMemberId) {
            $member = Member::where('club_id', $club->id)->findOrFail($this->editingMemberId);
            $member->update($data);
            $message = "Updated member {$member->formatted_rank_name}.";
        } else {
            $member = Member::create($data);
            $message = "Added new member {$member->formatted_rank_name}.";
        }

        if ($member->customerAccount) {
            $member->customerAccount->update([
                'email' => $this->email ?: $member->customerAccount->email,
                'phone' => $this->phone ?: $member->customerAccount->phone,
                'address_line_1' => $this->address_line_1 ?: null,
                'address_line_2' => $this->address_line_2 ?: null,
                'city' => $this->city ?: null,
                'state' => $this->county ?: null,
                'postcode' => $this->postcode ?: null,
                'country' => $this->country ?: null,
            ]);
        }

        // Cross-club address sync for user account
        if ($member->user_id) {
            $siblings = Member::where('user_id', $member->user_id)
                ->where('id', '!=', $member->id)
                ->get();

            foreach ($siblings as $sibling) {
                $sibling->update([
                    'address_line_1' => $this->address_line_1 ?: null,
                    'address_line_2' => $this->address_line_2 ?: null,
                    'city' => $this->city ?: null,
                    'county' => $this->county ?: null,
                    'postcode' => $this->postcode ?: null,
                    'country' => $this->country ?: null,
                ]);

                if ($sibling->customerAccount) {
                    $sibling->customerAccount->update([
                        'address_line_1' => $this->address_line_1 ?: null,
                        'address_line_2' => $this->address_line_2 ?: null,
                        'city' => $this->city ?: null,
                        'state' => $this->county ?: null,
                        'postcode' => $this->postcode ?: null,
                        'country' => $this->country ?: null,
                    ]);
                }
            }
        }

        $this->closeModal();
        session()->flash('success', $message);
    }

    public function deleteMember(int $memberId): void
    {
        $club = $this->getClub();
        $member = Member::where('club_id', $club->id)->findOrFail($memberId);
        $name = $member->full_name;
        $member->delete();

        session()->flash('success', "Member {$name} removed from roster.");
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

        $filename = "Lodge-Roster-{$club->slug}-" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($members, $club) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Hermes/GL ID',
                'Title',
                'First Name',
                'Last Name',
                'Masonic Rank',
                'Grand Rank',
                'Provincial Rank',
                'Current Office',
                'Status',
                'Email',
                'Phone',
                'Address Line 1',
                'Address Line 2',
                'City',
                'Postcode',
                'Date of Joining',
                'Date of Initiation',
            ]);

            foreach ($members as $m) {
                fputcsv($file, [
                    $m->grand_lodge_number,
                    $m->title,
                    $m->first_name,
                    $m->last_name,
                    $m->masonic_rank,
                    $m->grand_rank,
                    $m->provincial_rank,
                    $m->current_office?->label(),
                    $m->membership_status?->label(),
                    $m->email,
                    $m->phone,
                    $m->address_line_1,
                    $m->address_line_2,
                    $m->city,
                    $m->postcode,
                    $m->date_of_joining?->format('Y-m-d'),
                    $m->date_of_initiation?->format('Y-m-d'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    public function render()
    {
        $club = $this->getClub();

        $query = Member::where('club_id', $club->id)
            ->search($this->search)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('membership_status', $this->statusFilter))
            ->when($this->officeFilter !== 'all', fn ($q) => $q->where('current_office', $this->officeFilter))
            ->when($this->rankFilter !== 'all', fn ($q) => $q->where('masonic_rank', $this->rankFilter));

        if ($this->sortField === 'full_name') {
            $query->orderBy('last_name', $this->sortDirection)->orderBy('first_name', $this->sortDirection);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $members = $query->paginate(15);

        // Stats summary
        $totalMembers = Member::where('club_id', $club->id)->count();
        $activeCount = Member::where('club_id', $club->id)->active()->count();
        $officerCount = Member::where('club_id', $club->id)->officers()->count();
        $pmCount = Member::where('club_id', $club->id)->pastMasters()->count();

        $accountingContacts = AccountingContact::where('club_id', $club->id)->get();

        return view('livewire.members.member-index', [
            'club' => $club,
            'members' => $members,
            'totalMembers' => $totalMembers,
            'activeCount' => $activeCount,
            'officerCount' => $officerCount,
            'pmCount' => $pmCount,
            'accountingContacts' => $accountingContacts,
            'offices' => LodgeOffice::cases(),
            'statuses' => MembershipStatus::cases(),
        ])->layout('components.layouts.app', [
            'title' => 'Lodge Member Directory',
            'club' => $club,
        ]);
    }
}
