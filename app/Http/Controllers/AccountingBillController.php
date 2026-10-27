<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\LinksAccountingAttachments;
use App\Http\Requests\AccountingAdmin\StoreBillRequest;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Services\AccountingService;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor bills: create, publish, edit, pay, approve, delete. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingBillController extends Controller
{
    use LinksAccountingAttachments;

    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function createBill(Request $request, string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        return Inertia::render('Admin/Accounting/BillForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'initialVendor' => $request->query('vendor', ''),
        ]);
    }

    public function storeBill(StoreBillRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $attachmentFile = $request->hasFile('attachment') ? $request->file('attachment') : null;

        $bill = $this->accountingService->createVendorBill($club, $validated, $attachmentFile);

        $msg = match ($bill->status) {
            DocumentStatus::Draft->value => 'Vendor bill saved as draft.',
            DocumentStatus::PendingApproval->value => 'Vendor bill recorded — it is over the approval threshold, so another admin or treasurer needs to approve it before it is posted.',
            default => 'Vendor bill recorded and posted to Accounts Payable.',
        };

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'purchases'])
            ->with('success', $msg);
    }

    public function publishBill(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($bill->status === DocumentStatus::Draft->value) {
            $newStatus = $this->accountingService->issuedStatusFor($club, (float) $bill->amount);

            \DB::transaction(function () use ($club, $bill, $newStatus) {
                $bill->update(['status' => $newStatus]);

                if ($newStatus === DocumentStatus::Unpaid->value) {
                    $this->accountingService->postVendorBillIssuedJournal($club, $bill);
                }
            });

            if ($newStatus === DocumentStatus::Unpaid->value) {
                return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} published and posted to Accounts Payable.");
            }

            return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} is over the approval threshold, so another admin or treasurer needs to approve it before it is posted.");
        }

        return redirect()->back();
    }

    /**
     * Remove bill receipt attachment directly from Accounting.
     */
    public function deleteBillAttachment(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($bill->media_id && $bill->media) {
            $bill->media->forceDelete();
            $bill->update(['media_id' => null]);
        }

        return redirect()->back()->with('success', 'Bill receipt attachment removed.');
    }

    /**
     * Delete vendor bill and its attachment directly from Accounting.
     */
    public function destroyBill(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($bill->status === DocumentStatus::Paid->value) {
            return redirect()->back()->with('error', 'Paid bills cannot be deleted. Void or adjust via journal entry.');
        }

        if ($bill->media_id && $bill->media) {
            $bill->media->forceDelete();
        }

        JournalEntry::where('club_id', $club->id)
            ->where('source_type', 'VendorBill')
            ->where('source_id', $bill->id)
            ->update(['status' => DocumentStatus::Void->value]);

        $this->accountingService->log($club, 'bill', $bill->id, 'voided', "Vendor bill {$bill->bill_number} ({$bill->vendor_name}) deleted.");

        $bill->delete();

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} deleted.");
    }

    public function updateBill(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $billStatusOptions = match ($bill->status) {
            DocumentStatus::Paid->value => [DocumentStatus::Draft->value, DocumentStatus::Unpaid->value, DocumentStatus::Paid->value],
            // unpaid is deliberately not offered here while pending_approval: that
            // transition only happens through approveBill(), which enforces a
            // different approver and posts the issued journal entry.
            DocumentStatus::PendingApproval->value => [DocumentStatus::Draft->value, DocumentStatus::PendingApproval->value],
            default => [DocumentStatus::Draft->value, DocumentStatus::Unpaid->value],
        };

        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', Rule::in($billStatusOptions)],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);

        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            if ($bill->media_id && $bill->media) {
                $bill->media->forceDelete();
            }
            $media = $club->addMedia($request->file('attachment'))
                ->withCustomProperties([
                    'is_accounting_protected' => true,
                    'source' => 'accounting',
                    'bill_number' => $bill->bill_number,
                    'vendor_name' => $validated['vendor_name'],
                ])
                ->toMediaCollection('accounting', 'local');
            $validated['media_id'] = $media->id;
        }

        $wasDraft = $bill->status === DocumentStatus::Draft->value;
        $before = $bill->only(['vendor_name', 'amount', 'status']);
        $vat = $this->accountingService->resolveVatFields($club, (float) $validated['amount'], $bill->vat_rate !== null ? (float) $bill->vat_rate : null);

        // Publishing a draft still has to respect the approval threshold, even though
        // the requested status was 'unpaid' — otherwise saving as draft first would be
        // a free bypass.
        $finalStatus = ($wasDraft && $validated['status'] === DocumentStatus::Unpaid->value)
            ? $this->accountingService->issuedStatusFor($club, $vat['amount'])
            : $validated['status'];

        $bill->update([
            'vendor_name' => $validated['vendor_name'],
            'category' => $validated['category'],
            'amount' => $vat['amount'],
            'net_amount' => $vat['net_amount'],
            'vat_rate' => $vat['vat_rate'],
            'vat_amount' => $vat['vat_amount'],
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'] ?? null,
            'status' => $finalStatus,
            'media_id' => $validated['media_id'] ?? $bill->media_id,
        ]);

        $this->accountingService->log($club, 'bill', $bill->id, 'updated', "Vendor bill {$bill->bill_number} updated.", before: $before, after: $bill->only(['vendor_name', 'amount', 'status']));

        if ($wasDraft && $finalStatus === DocumentStatus::Unpaid->value) {
            $this->accountingService->postVendorBillIssuedJournal($club, $bill);
        }

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} updated successfully.");
    }

    public function editBill(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        return Inertia::render('Admin/Accounting/BillForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'bill' => [
                'id' => $bill->id,
                'vendor_name' => $bill->vendor_name,
                'category' => $bill->category,
                'amount' => (float) $bill->amount,
                'due_date' => $bill->due_date->format('Y-m-d'),
                'notes' => $bill->notes ?? '',
                'status' => $bill->status,
                'bill_number' => $bill->bill_number,
                'attachment_url' => $bill->media_id && $bill->media ? $this->attachmentUrl($club, $bill->media) : null,
                'created_at' => $bill->created_at->format('d M Y'),
            ],
        ]);
    }

    public function markBillPaid(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $this->accountingService->markBillAsPaid($bill, $request->user());

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} marked as paid.");
    }

    public function approveBill(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        try {
            $this->accountingService->approveBill($bill, $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} approved and posted to Accounts Payable.");
    }
}
