<?php

namespace App\Http\Requests\NewsletterAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\UploadRules;
use Illuminate\Validation\Rule;

/**
 * The form posted to NewsletterAdminController::store().
 */
class StoreNewsletterRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'id' => 'nullable|integer',
            'newsletter_type_id' => ['nullable', Rule::exists('newsletter_types', 'id')->where('club_id', $club->id)],
            'subject' => 'required|string|max:255',
            'content' => 'required|string|max:200000',
            'target_roles' => 'required|array|max:10',
            'status' => 'required|in:draft,sent',
            'existing_attachments' => 'nullable|array|max:50',
            'new_attachments.*' => UploadRules::attachment(10240),
        ];
    }
}
