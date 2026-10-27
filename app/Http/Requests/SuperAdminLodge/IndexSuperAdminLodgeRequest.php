<?php

namespace App\Http\Requests\SuperAdminLodge;

use App\Http\Requests\AppRequest;
use App\Models\Lodge;
use Illuminate\Validation\Rule;

/**
 * The form posted to SuperAdminLodgeController::index().
 */
class IndexSuperAdminLodgeRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:60',
            'order' => 'nullable|string|max:60',
            'status' => ['nullable', Rule::in([...Lodge::STATUSES, 'managed'])],
            'gaps' => 'nullable|in:no_hall,no_schedule,no_province',
        ];
    }
}
