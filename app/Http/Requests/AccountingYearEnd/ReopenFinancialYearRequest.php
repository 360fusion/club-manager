<?php

namespace App\Http\Requests\AccountingYearEnd;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingYearEndController::reopenFinancialYear().
 */
class ReopenFinancialYearRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],

        ];
    }
}
