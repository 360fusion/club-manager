<?php

namespace App\Http\Requests\Club;

use App\Http\Requests\ClubRequest;
use App\Support\ClubDomain;

/**
 * The form posted to ClubController::updateDomain().
 */
class UpdateDomainRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'custom_domain' => ClubDomain::rule($club),
        ];
    }
}
