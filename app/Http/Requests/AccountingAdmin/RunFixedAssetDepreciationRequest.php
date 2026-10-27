<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;

/**
 * The form posted to AccountingAdminController::runFixedAssetDepreciation().
 */
class RunFixedAssetDepreciationRequest extends ClubRequest
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
            'period' => ['required', 'string', 'max:20'],
        ];
    }
}
