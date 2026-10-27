<?php

namespace App\Http\Requests\NewsletterTypeAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to NewsletterTypeAdminController::updateSubscriberStatus().
 */
class UpdateSubscriberStatusRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:active,pending_approval,unsubscribed,rejected',

        ];
    }
}
