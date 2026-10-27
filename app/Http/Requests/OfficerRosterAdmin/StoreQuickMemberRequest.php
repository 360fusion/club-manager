<?php

namespace App\Http\Requests\OfficerRosterAdmin;

use App\Domains\ClubAccounting\Models\Member;
use App\Http\Requests\ClubRequest;
use App\Support\MasonicRanks;
use Illuminate\Validation\Rule;

/**
 * The form posted to OfficerRosterAdminController::storeQuickMember().
 */
class StoreQuickMemberRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'membership_status' => 'nullable|string|max:50',
            'masonic_rank' => ['nullable', Rule::in(array_keys(Member::MASONIC_RANKS))],
            'grand_rank' => ['nullable', 'string', 'max:100', Rule::in(MasonicRanks::allowedValues($club, MasonicRanks::GRAND))],
            'provincial_rank' => ['nullable', 'string', 'max:100', Rule::in(MasonicRanks::allowedValues($club, MasonicRanks::PROVINCIAL))],
        ];
    }
}
