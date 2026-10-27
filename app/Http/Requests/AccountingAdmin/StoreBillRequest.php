<?php

namespace App\Http\Requests\AccountingAdmin;

use App\Http\Requests\ClubRequest;

/**
 * The form posted to AccountingAdminController::storeBill().
 */
class StoreBillRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vendor_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'is_draft' => ['nullable', 'boolean'],
        ];
    }
}
