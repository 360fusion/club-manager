<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to PageAdminController::geocode().
 */
class GeocodeRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => 'required|string|max:200',
        ];
    }
}
