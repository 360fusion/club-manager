<?php

namespace App\Http\Requests\SuperAdminLodge;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminLodgeController::linkClub().
 */
class LinkClubRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'club_id' => 'required|exists:clubs,id',
        ];
    }
}
