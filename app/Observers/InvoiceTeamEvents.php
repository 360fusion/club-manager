<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Support\ClubTeamEvents;

/** An invoice raised, and an invoice paid (also when it is raised already paid) */
class InvoiceTeamEvents
{
    public function created(Invoice $invoice): void
    {
        $this->record($invoice, 'invoice.created', 'raised the invoice');
        $invoice->status === 'paid' && $this->record($invoice, 'invoice.paid', 'recorded as paid the invoice');
    }

    public function updated(Invoice $invoice): void
    {
        $invoice->wasChanged('status') && $invoice->status === 'paid' && $this->record($invoice, 'invoice.paid', 'recorded as paid the invoice');
    }

    private function record(Invoice $invoice, string $event, string $verb): void
    {
        ClubTeamEvents::record((int) $invoice->club_id, $event, "{$verb} {$invoice->invoice_number} ({$invoice->title})", [
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'title' => $invoice->title,
            'amount' => $invoice->amount, 'user_id' => $invoice->user_id,
        ]);
    }
}
