<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;
use Illuminate\Validation\Rule;

/**
 * The form posted to AccountingAdminController::storeJournalEntry().
 */
class StoreJournalEntryRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'description' => ['required', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:2', 'max:100'],
            'items.*.account_id' => ['required', Rule::exists('accounting_accounts', 'id')->where('club_id', $club->id)],
            'items.*.debit' => ['numeric', 'min:0'],
            'items.*.credit' => ['numeric', 'min:0'],
            'items.*.memo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
