<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AttendanceController::walkIn().
 */
class WalkInRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'dietary_requirements' => 'nullable|string|max:1000',

        ];
    }
}
