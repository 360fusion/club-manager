<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingAdminController::importBankStatement().
 */
class ImportBankStatementRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statement_file' => 'required|file|mimes:csv,txt,ofx,qfx|max:10240',
        ];
    }
}
