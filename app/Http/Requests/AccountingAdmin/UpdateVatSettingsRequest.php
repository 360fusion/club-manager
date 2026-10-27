<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;

/**
 * The form posted to AccountingAdminController::updateVatSettings().
 */
class UpdateVatSettingsRequest extends ClubRequest
{
    public function authorize(): bool
    {
        return ClubAccess::can($this->user(), $this->club(), 'manage_billing');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'scheme' => ['required', 'string', 'in:not_registered,standard,flat_rate,cash_accounting,annual_accounting'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'flat_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'registered_from' => ['nullable', 'date'],
        ];
    }
}
