<?php

namespace App\Http\Requests\SuperAdmin;

use App\Http\Requests\AppRequest;
use App\Support\OrderColours;
use Illuminate\Validation\Rule;

/**
 * The form posted to SuperAdminController::storeClubType().
 */
class StoreClubTypeRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:club_types,code',
            'colour' => ['nullable', Rule::in(OrderColours::KEYS)],
            'description' => 'nullable|string|max:10000',
            'website_url' => 'nullable|url|max:255',
            'available_modules' => 'nullable|array|max:50',
            'terminology' => 'nullable|array|max:100',
            'rulers_schema' => 'nullable|array|max:100',
        ];
    }
}
