<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingAdminController::reconcileBankTransaction().
 */
class ReconcileBankTransactionRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => 'required|integer',
            'match_type' => 'required|string|max:50',
            'target_id' => 'required',
            'nominal_code' => 'nullable|string|max:255',
        ];
    }
}
