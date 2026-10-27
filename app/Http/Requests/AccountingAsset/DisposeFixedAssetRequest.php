<?php

namespace App\Http\Requests\AccountingAsset;

use App\Http\Requests\ClubRequest;
use App\Support\ClubAccess;

/**
 * The form posted to AccountingAssetController::disposeFixedAsset().
 */
class DisposeFixedAssetRequest extends ClubRequest
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
            'disposal_date' => ['required', 'date'],
            'disposal_proceeds' => ['required', 'numeric', 'min:0'],

        ];
    }
}
