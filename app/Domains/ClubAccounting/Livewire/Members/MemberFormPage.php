<?php

namespace App\Domains\ClubAccounting\Livewire\Members;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberInvitationException;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use App\Support\ClubAccess;
use App\Support\MasonicRanks;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Add a member to the roster, or edit one, on a page of its own (there is too much to fit in a pop-up).
 */
class MemberFormPage extends Component
{
    #[Locked]
    public string $clubSlug;

    #[Locked]
    public ?int $memberId = null;

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

    public bool $sendInvitation = false;

    public function mount(string $clubSlug, ?int $memberId = null): void
    {
        $this->clubSlug = $clubSlug;
        $club = $this->getClub();

        if ($memberId === null) {
            return;
        }

        $member = Member::where('club_id', $club->id)->findOrFail($memberId);

        $this->memberId = $member->id;
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

    public function save()
    {
        $club = $this->authorizedClub();

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
            'grand_rank' => ['nullable', 'string', 'max:255', Rule::in(MasonicRanks::allowedValues($club, MasonicRanks::GRAND, $this->storedRank('grand_rank')))],
            'provincial_rank' => ['nullable', 'string', 'max:255', Rule::in(MasonicRanks::allowedValues($club, MasonicRanks::PROVINCIAL, $this->storedRank('provincial_rank')))],
            'membership_status' => 'required|in:'.implode(',', array_column(MembershipStatus::cases(), 'value')),
            'current_office' => 'required|in:'.implode(',', array_column(LodgeOffice::cases(), 'value')),
            'grand_lodge_number' => 'nullable|string|max:50',
            'date_of_initiation' => 'nullable|date',
            'date_of_passing' => 'nullable|date',
            'date_of_raising' => 'nullable|date',
            'date_of_joining' => 'nullable|date',
            'customer_account_id' => 'nullable|integer',
        ], [
            'grand_rank.in' => 'Choose a Grand rank from the list.',
            'provincial_rank.in' => 'Choose a Provincial rank from the list.',
        ]);

        $data = [
            'club_id' => $club->id,
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
            'customer_account_id' => $this->ledgerContactId($club),
        ];

        if ($this->memberId) {
            $member = Member::where('club_id', $club->id)->findOrFail($this->memberId);
            $member->update($data);
            $message = "Updated member {$member->formatted_rank_name}.";
        } else {
            $member = Member::create($data);
            $message = "Added new member {$member->formatted_rank_name}.";

            if ($this->sendInvitation && $member->email) {
                $message .= $this->invitationNote($member, $club);
            }
        }

        $this->syncAddressElsewhere($member);

        session()->flash('success', $message);

        return $this->memberId
            ? redirect()->route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $member->id])
            : redirect()->route('admin.club_acc.members.index', ['clubSlug' => $club->slug]);
    }

    /**
     * The rank the member being edited already holds, so it stays valid even if it is not in the lodge's list.
     */
    private function storedRank(string $field): ?string
    {
        if (! $this->memberId) {
            return null;
        }

        return Member::where('club_id', $this->getClub()->id)->whereKey($this->memberId)->value($field);
    }

    /**
     * The chosen ledger contact, only if it belongs to this club.
     */
    private function ledgerContactId(Club $club): ?int
    {
        if (! $this->customer_account_id) {
            return null;
        }

        return AccountingContact::where('club_id', $club->id)->whereKey($this->customer_account_id)->value('id');
    }

    /**
     * Keep the ledger contact, and the same person's other lodge records, in step with this address.
     */
    private function syncAddressElsewhere(Member $member): void
    {
        $address = [
            'address_line_1' => $this->address_line_1 ?: null,
            'address_line_2' => $this->address_line_2 ?: null,
            'city' => $this->city ?: null,
            'postcode' => $this->postcode ?: null,
            'country' => $this->country ?: null,
        ];

        if ($member->customerAccount) {
            $member->customerAccount->update($address + [
                'email' => $this->email ?: $member->customerAccount->email,
                'phone' => $this->phone ?: $member->customerAccount->phone,
                'state' => $this->county ?: null,
            ]);
        }

        if (! $member->user_id) {
            return;
        }

        Member::where('user_id', $member->user_id)->where('id', '!=', $member->id)->get()->each(function (Member $sibling) use ($address) {
            $sibling->update($address + ['county' => $this->county ?: null]);

            $sibling->customerAccount?->update($address + ['state' => $this->county ?: null]);
        });
    }

    private function invitationNote(Member $member, Club $club): string
    {
        try {
            $sent = app(MemberInvitationService::class)->invite($member, $club, auth()->user());

            return $sent['emailed'] ? " Invitation emailed to {$member->email}." : " Email could not be sent. Share this link: {$sent['url']}";
        } catch (MemberInvitationException $e) {
            return " Not invited: {$e->getMessage()}.";
        }
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    private function authorizedClub(): Club
    {
        $club = $this->getClub();
        ClubAccess::authorize(auth()->user(), $club, 'manage_members');

        return $club;
    }

    public function render()
    {
        $club = $this->getClub();

        return view('livewire.members.member-form', [
            'club' => $club,
            'editing' => $this->memberId !== null,
            'accountingContacts' => AccountingContact::where('club_id', $club->id)->get(),
            'ranks' => Member::MASONIC_RANKS,
            'grandRanks' => MasonicRanks::optionsFor($club, MasonicRanks::GRAND, $this->storedRank('grand_rank')),
            'provincialRanks' => MasonicRanks::optionsFor($club, MasonicRanks::PROVINCIAL, $this->storedRank('provincial_rank')),
            'offices' => LodgeOffice::cases(),
            'statuses' => MembershipStatus::cases(),
            'canInvite' => ClubAccess::can(auth()->user(), $club, 'manage_members'),
        ])->layout('components.layouts.app', [
            'title' => $this->memberId ? 'Edit Member' : 'Add Member',
            'club' => $club,
        ]);
    }
}
