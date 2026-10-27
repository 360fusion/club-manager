<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingAdminController::ignoreBankTransaction().
 */
class IgnoreBankTransactionRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => 'required|integer',
        ];
    }
}
