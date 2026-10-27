<?php

namespace App\Http\Requests\LodgeVisitor;

use App\Http\Requests\AppRequest;

/**
 * The form posted to LodgeVisitorController::storeVisit(): asking to attend one of a lodge's meetings.
 */
class StoreLodgeVisitRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meeting_date' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'home_lodge_name' => 'required|string|max:255',
            'home_lodge_number' => 'nullable|string|max:20',
            'rank' => 'nullable|string|max:100',
            'dining' => 'boolean',
            'message' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['meeting_date.after' => 'Choose a meeting date in the future.', 'meeting_date.before_or_equal' => 'Choose a date within the next year.'];
    }
}
