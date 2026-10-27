<?php

namespace App\Http\Requests\MeetingAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\UploadRules;
use Illuminate\Validation\Rule;

/**
 * The form posted to MeetingAdminController::store().
 */
class StoreMeetingRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'id' => ['nullable', Rule::exists('meetings', 'id')->where('club_id', $club->id)],
            'title' => 'required|string|max:255',
            'meeting_number' => 'nullable|integer|max:1000000',
            'meeting_date' => 'required|date',
            'starts_at' => 'required|string|max:20',
            'rehearsal_starts_at' => 'nullable|string|max:20',
            'venue' => 'required|string|max:255',
            'dress_code' => 'required|string|max:255',
            'salutation' => 'nullable|string|max:255',
            'intro_text' => 'nullable|string|max:10000',
            'rehearsal_text' => 'nullable|string|max:10000',
            'festive_board_theme' => 'nullable|string|max:255',
            'festive_board_menu' => 'nullable|string|max:10000',
            'dining_cost_member' => 'required|numeric|min:0|max:99999999.99',
            'dining_cost_guest' => 'required|numeric|min:0|max:99999999.99',
            'bank_sort_code' => 'nullable|string|max:20',
            'bank_account_number' => 'nullable|string|max:30',
            'payment_reference_prefix' => 'nullable|string|max:50',
            'payment_link' => 'nullable|string|max:500',
            'almoner_notice' => 'nullable|string|max:10000',
            'sick_distressed_notes' => 'nullable|string|max:10000',
            'honorary_members_text' => 'nullable|string|max:10000',
            'provincial_header_text' => 'nullable|string|max:10000',
            'fraternal_visits_text' => 'nullable|string|max:10000',
            'officers_year_label' => 'nullable|string|max:255',
            'officers_roster' => 'nullable|array|max:100',
            'front_page_logo' => 'nullable|string|max:1000',
            'front_page_logo_file' => UploadRules::image(5120),
            'front_page_title' => 'nullable|string|max:255',
            'provincial_grand_master' => 'nullable|string|max:255',
            'deputy_provincial_grand_master' => 'nullable|string|max:255',
            'assistant_provincial_grand_masters' => 'nullable|string|max:10000',
            'cover_club_name' => 'nullable|string|max:255',
            'cover_club_number' => 'nullable|string|max:100',
            'cover_motto' => 'nullable|string|max:255',
            'cover_worshipful_master' => 'nullable|string|max:255',
            'status' => 'required|in:draft,published,completed,cancelled',
            'agenda_items' => 'nullable|array|max:100',
        ];
    }
}
