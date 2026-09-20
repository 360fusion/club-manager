<?php

namespace App\Domains\ClubAccounting\Livewire\Committee\Modals;

use App\Domains\ClubAccounting\Enums\CommitteeItemType;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Accounting\Bill;
use Livewire\Component;

class BillAuditModal extends Component
{
    public int $meetingId;

    public ?int $billId = null;

    public bool $isOpen = false;

    public string $auditNotes = '';

    public bool $isApprovedForPayment = true;

    protected $listeners = ['openBillAudit' => 'loadBill'];

    public function loadBill(int $billId, int $meetingId): void
    {
        $this->billId = $billId;
        $this->meetingId = $meetingId;
        $this->isOpen = true;
        $this->auditNotes = 'Audited under Rule 158. Supporting voucher/receipt inspected and confirmed against ledger expense code.';
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['billId', 'auditNotes']);
    }

    public function signOffBill(): void
    {
        $bill = Bill::with('media')->findOrFail($this->billId);
        $meeting = ClubCommitteeMeeting::findOrFail($this->meetingId);

        ClubCommitteeAgendaItem::create([
            'committee_meeting_id' => $meeting->id,
            'order' => $meeting->agendaItems()->count() + 1,
            'item_type' => CommitteeItemType::AccountsAudit,
            'title' => "Bill Audit: {$bill->vendor_name} ({$bill->bill_number})",
            'description' => "Audit of {$bill->vendor_name} for £".number_format($bill->amount, 2)." ({$bill->category}).",
            'discussion_notes' => $this->auditNotes,
            'recommendation_text' => $this->isApprovedForPayment
                ? "AUDITED & APPROVED: Committee recommends payment of bill {$bill->bill_number} (£".number_format($bill->amount, 2).') to open lodge.'
                : 'QUERY RAISED: Bill payment held pending clarification with vendor.',
            'is_approved' => $this->isApprovedForPayment,
            'reference_id' => $bill->id,
            'reference_type' => Bill::class,
        ]);

        $this->closeModal();
        $this->dispatch('agendaItemAdded');
        session()->flash('success', "Vendor bill {$bill->bill_number} audit sign-off recorded on the committee agenda.");
    }

    public function render()
    {
        $bill = $this->billId ? Bill::with('media')->find($this->billId) : null;

        return view('livewire.committee.bill-audit-modal', [
            'bill' => $bill,
        ]);
    }
}
