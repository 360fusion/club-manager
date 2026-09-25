<?php

namespace App\Domains\ClubAccounting\Livewire\Members;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Concerns\ShowsNotice;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberInvitationException;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use App\Models\User;
use App\Support\ClubAccess;
use App\Support\MasonicRanks;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MemberProfile extends Component
{
    use ShowsNotice;

    #[Locked]
    public string $clubSlug;

    #[Locked]
    public int $memberId;

    public string $activeTab = 'details'; // details, finances

    // Quick Edit fields
    public bool $isEditing = false;

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

    public string $masonic_rank = '';

    public string $grand_rank = '';

    public string $provincial_rank = '';

    public string $grand_lodge_number = '';

    public string $membership_status = '';

    public string $current_office = '';

    public ?string $date_of_initiation = null;

    public ?string $date_of_passing = null;

    public ?string $date_of_raising = null;

    public ?string $date_of_joining = null;

    public ?string $annual_dues_override = null;

    public string $notes = '';

    public ?int $customer_account_id = null;

    public function mount(string $clubSlug, int $memberId): void
    {
        $this->clubSlug = $clubSlug;
        $this->memberId = $memberId;
        $this->loadMemberData();
    }

    public function loadMemberData(): void
    {
        $member = $this->getMember();

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
    }

    public function toggleEdit(): void
    {
        $this->isEditing = ! $this->isEditing;
        if (! $this->isEditing) {
            $this->loadMemberData();
        }
    }

    public function updateProfile(): void
    {
        $stored = $this->getMember();

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
            'masonic_rank' => 'required|in:'.implode(',', array_keys(Member::MASONIC_RANKS)),
            'grand_rank' => ['nullable', 'string', 'max:255', Rule::in(MasonicRanks::allowedValues($stored->club, MasonicRanks::GRAND, $stored->grand_rank))],
            'provincial_rank' => ['nullable', 'string', 'max:255', Rule::in(MasonicRanks::allowedValues($stored->club, MasonicRanks::PROVINCIAL, $stored->provincial_rank))],
            'membership_status' => 'required|string|max:50',
            'current_office' => 'nullable|string|max:50',
            'date_of_initiation' => 'nullable|date',
            'date_of_passing' => 'nullable|date',
            'date_of_raising' => 'nullable|date',
            'date_of_joining' => 'nullable|date',
            'customer_account_id' => ['nullable', 'integer', Rule::exists('accounting_contacts', 'id')->where('club_id', $stored->club_id)],
        ]);

        $member = $this->getMember();

        $member->update([
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
            'current_office' => $this->current_office ? LodgeOffice::from($this->current_office) : $member->current_office,
            'date_of_initiation' => $this->date_of_initiation ?: null,
            'date_of_passing' => $this->date_of_passing ?: null,
            'date_of_raising' => $this->date_of_raising ?: null,
            'date_of_joining' => $this->date_of_joining ?: null,
            'annual_dues_override' => $this->annual_dues_override ? (float) $this->annual_dues_override : null,
            'notes' => $this->notes ?: null,
            'customer_account_id' => $this->customer_account_id ?: null,
        ]);

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

        $this->isEditing = false;
        $this->notify("Updated profile for {$member->formatted_rank_name}.");
    }

    public function archiveMember(): void
    {
        $member = $this->getMember();
        $member->update(['membership_status' => MembershipStatus::Resigned]);
        $this->membership_status = MembershipStatus::Resigned->value;
        $this->notify("{$member->formatted_rank_name} marked as Resigned / Archived.");
    }

    public function deleteMember()
    {
        $member = $this->getMember();
        $name = $member->formatted_rank_name;
        $member->delete();

        session()->flash('success', "Member {$name} removed from roster.");

        return redirect()->route('admin.club_acc.members.index', ['clubSlug' => $this->clubSlug]);
    }

    public function inviteToPortal(MemberInvitationService $invitations): void
    {
        $member = $this->getMember();
        $club = $member->club;
        ClubAccess::authorize(auth()->user(), $club, 'manage_members');

        try {
            $sent = $invitations->invite($member, $club, auth()->user());
            $this->notify($sent['emailed'] ? "Invitation emailed to {$member->email}." : "Email could not be sent. Share this link: {$sent['url']}");
        } catch (MemberInvitationException $e) {
            $this->notify($e->getMessage().'.', 'error');
        }
    }

    public function resendPortalInvite(MemberInvitationService $invitations): void
    {
        $member = $this->getMember();
        $club = $member->club;
        ClubAccess::authorize(auth()->user(), $club, 'manage_members');

        try {
            $sent = $invitations->resend($member, $club, auth()->user());
            $this->notify($sent['emailed'] ? "Invitation sent again to {$member->email}." : "Invitation renewed. Share this link: {$sent['url']}");
        } catch (MemberInvitationException $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function revokePortalInvite(MemberInvitationService $invitations): void
    {
        $member = $this->getMember();
        $club = $member->club;
        ClubAccess::authorize(auth()->user(), $club, 'manage_members');

        if ($member->user) {
            $invitations->revoke($club, $member->user);
        }

        $this->notify('Invitation withdrawn.');
    }

    public function getMember(): Member
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();

        return Member::where('club_id', $club->id)
            ->where('id', $this->memberId)
            ->with(['club', 'user', 'customerAccount', 'subscriptionTier', 'subscriptions.tier'])
            ->firstOrFail();
    }

    public function render()
    {
        $member = $this->getMember();
        $club = $member->club;

        $accountingContacts = AccountingContact::where('club_id', $club->id)->get();

        $invitations = app(MemberInvitationService::class);
        $accountPivot = $invitations->pivotFor($member, $club);

        return view('livewire.members.member-profile', [
            'club' => $club,
            'member' => $member,
            'account' => $member->accountStatus($club),
            'accountPivot' => $accountPivot,
            'accountInviter' => $accountPivot?->invited_by ? User::find($accountPivot->invited_by) : null,
            'accountExpiresAt' => $accountPivot?->invited_at ? Carbon::parse($accountPivot->invited_at)->addDays($club->inviteExpirationDays()) : null,
            'canInvite' => ClubAccess::can(auth()->user(), $club, 'manage_members'),
            'accountingContacts' => $accountingContacts,
            'ranks' => Member::MASONIC_RANKS,
            'grandRanks' => MasonicRanks::optionsFor($club, MasonicRanks::GRAND, $member->grand_rank),
            'provincialRanks' => MasonicRanks::optionsFor($club, MasonicRanks::PROVINCIAL, $member->provincial_rank),
            'offices' => LodgeOffice::cases(),
            'statuses' => MembershipStatus::cases(),
        ])->layout('components.layouts.app', [
            'title' => $member->formatted_rank_name.' — Profile',
            'club' => $club,
        ]);
    }
}
