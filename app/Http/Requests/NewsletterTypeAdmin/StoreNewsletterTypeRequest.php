<?php

namespace App\Http\Requests\NewsletterTypeAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to NewsletterTypeAdminController::store().
 */
class StoreNewsletterTypeRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'color' => 'required|string|max:20',
            'icon' => 'required|string|max:10',
            'is_external_subscribable' => 'required|boolean',
            'require_approval' => 'required|boolean',
            'is_mandatory' => 'required|boolean',
            'require_home_club_info' => 'required|boolean',
            'default_roles' => 'nullable|array|max:10',
            'sender_name' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
        ];
    }
}
