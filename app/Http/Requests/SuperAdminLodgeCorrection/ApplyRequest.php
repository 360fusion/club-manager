<?php

namespace App\Http\Requests\SuperAdminLodgeCorrection;

use App\Http\Requests\AppRequest;
use App\Models\Lodge;
use App\Models\LodgeCorrection;
use Illuminate\Validation\Rule;

/**
 * The form posted to SuperAdminLodgeCorrectionController::apply().
 */
class ApplyRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => 'required|array|max:7',
            'fields.*' => Rule::in(array_keys(LodgeCorrection::FIELDS)),
            'values' => 'nullable|array|max:7',
            'values.meets_text' => 'nullable|string|max:500',
            'values.masonic_hall_id' => 'nullable|exists:masonic_halls,id',
            'values.status' => ['nullable', Rule::in(Lodge::STATUSES)],
            'values.name' => 'nullable|string|max:255',
            'values.number' => 'nullable|string|max:20',
            'values.website_url' => ['nullable', 'url', 'max:255', 'regex:#^https?://#i'],
            'note' => 'nullable|string|max:1000',
        ];
    }
}
