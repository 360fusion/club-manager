<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\LinksAccountingAttachments;
use App\Models\Accounting\JournalEntry;
use App\Models\Invoice;
use App\Services\AccountingService;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invoices: create, publish, edit, pay, approve, delete. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingInvoiceController extends Controller
{
    use LinksAccountingAttachments;

    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function createInvoice(Request $request, string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        $members = \DB::table('club_user')
            ->join('users', 'club_user.user_id', '=', 'users.id')
            ->where('club_user.club_id', $club->id)
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]);

        return Inertia::render('Admin/Accounting/InvoiceForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'members' => $members,
            // "Invoice this contact" on the contacts list opens the form with that member chosen.
            'initialUserId' => $members->firstWhere('id', $request->integer('user'))['id'] ?? null,
        ]);
    }

    public function storeInvoice(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $isDraft = $request->boolean('is_draft');

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)],
            'title' => [$isDraft ? 'nullable' : 'required', 'string', 'max:255'],
            'amount' => [$isDraft ? 'nullable' : 'required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'is_draft' => ['nullable', 'boolean'],
        ]);

        $title = ! empty($validated['title']) ? $validated['title'] : 'Draft Invoice';
        $amount = isset($validated['amount']) ? (float) $validated['amount'] : 0.00;
        $status = $isDraft ? DocumentStatus::Draft->value : $this->accountingService->issuedStatusFor($club, $amount);
        $vat = $this->accountingService->resolveVatFields($club, $amount, $request->float('vat_rate') ?: null);

        $invCount = Invoice::where('club_id', $club->id)->count() + 1;
        $invNum = 'INV-'.date('Y').'-'.str_pad((string) $invCount, 4, '0', STR_PAD_LEFT);

        $mediaId = null;
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $media = $club->addMedia($request->file('attachment'))
                ->withCustomProperties([
                    'is_accounting_protected' => true,
                    'source' => 'accounting',
                    'invoice_number' => $invNum,
                    'invoice_title' => $title,
                ])
                ->toMediaCollection('accounting', 'local');
            $mediaId = $media->id;
        }

        $invoice = Invoice::create([
            'club_id' => $club->id,
            'created_by' => $request->user()->id,
            'user_id' => $validated['user_id'],
            'invoice_number' => $invNum,
            'title' => $title,
            'amount' => $vat['amount'],
            'net_amount' => $vat['net_amount'],
            'vat_rate' => $vat['vat_rate'],
            'vat_amount' => $vat['vat_amount'],
            'status' => $status,
            'media_id' => $mediaId,
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($status === DocumentStatus::Unpaid->value) {
            $this->accountingService->postMemberInvoiceIssuedJournal($club, $invoice);
            $msg = 'Invoice created and posted to Accounts Receivable.';
        } elseif ($status === DocumentStatus::PendingApproval->value) {
            $msg = 'Invoice created — it is over the approval threshold, so another admin or treasurer needs to approve it before it is posted.';
        } else {
            $msg = 'Invoice saved as draft.';
        }

        return redirect()
            ->route('admin.accounting.index', ['clubSlug' => $clubSlug, 'tab' => 'sales'])
            ->with('success', $msg);
    }

    public function publishInvoice(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($invoice->status === DocumentStatus::Draft->value) {
            $newStatus = $this->accountingService->issuedStatusFor($club, (float) $invoice->amount);
            $invoice->update(['status' => $newStatus]);

            if ($newStatus === DocumentStatus::Unpaid->value) {
                $this->accountingService->postMemberInvoiceIssuedJournal($club, $invoice);

                return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} published and posted to Accounts Receivable.");
            }

            return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} is over the approval threshold, so another admin or treasurer needs to approve it before it is posted.");
        }

        return redirect()->back();
    }

    /**
     * Remove invoice document attachment directly from Accounting.
     */
    public function deleteInvoiceAttachment(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($invoice->media_id && $invoice->media) {
            $invoice->media->forceDelete();
            $invoice->update(['media_id' => null]);
        }

        return redirect()->back()->with('success', 'Invoice attachment removed.');
    }

    public function updateInvoice(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $invoiceStatusOptions = match ($invoice->status) {
            DocumentStatus::Paid->value => [DocumentStatus::Draft->value, DocumentStatus::Unpaid->value, DocumentStatus::Paid->value],
            // unpaid is deliberately not offered here while pending_approval: that
            // transition only happens through approveInvoice(), which enforces a
            // different approver and posts the issued journal entry.
            DocumentStatus::PendingApproval->value => [DocumentStatus::Draft->value, DocumentStatus::PendingApproval->value],
            default => [DocumentStatus::Draft->value, DocumentStatus::Unpaid->value],
        };

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', 'string', Rule::in($invoiceStatusOptions)],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);

        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            if ($invoice->media_id && $invoice->media) {
                $invoice->media->forceDelete();
            }
            $media = $club->addMedia($request->file('attachment'))
                ->withCustomProperties([
                    'is_accounting_protected' => true,
                    'source' => 'accounting',
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_title' => $validated['title'],
                ])
                ->toMediaCollection('accounting', 'local');
            $validated['media_id'] = $media->id;
        }

        $wasDraft = $invoice->status === DocumentStatus::Draft->value;
        $before = $invoice->only(['title', 'amount', 'status']);
        $vat = $this->accountingService->resolveVatFields($club, (float) $validated['amount'], $invoice->vat_rate !== null ? (float) $invoice->vat_rate : null);

        // Publishing a draft still has to respect the approval threshold, even though
        // the requested status was 'unpaid' — otherwise saving as draft first would be
        // a free bypass.
        $finalStatus = ($wasDraft && $validated['status'] === DocumentStatus::Unpaid->value)
            ? $this->accountingService->issuedStatusFor($club, $vat['amount'])
            : $validated['status'];

        $invoice->update([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'amount' => $vat['amount'],
            'net_amount' => $vat['net_amount'],
            'vat_rate' => $vat['vat_rate'],
            'vat_amount' => $vat['vat_amount'],
            'status' => $finalStatus,
            'media_id' => $validated['media_id'] ?? $invoice->media_id,
            // Left out of the request: kept. Sent empty: cleared.
            'due_date' => array_key_exists('due_date', $validated) ? $validated['due_date'] : $invoice->due_date,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $invoice->notes,
        ]);

        $this->accountingService->log($club, 'invoice', $invoice->id, 'updated', "Invoice {$invoice->invoice_number} updated.", before: $before, after: $invoice->only(['title', 'amount', 'status']));

        if ($wasDraft && $finalStatus === DocumentStatus::Unpaid->value) {
            $this->accountingService->postMemberInvoiceIssuedJournal($club, $invoice);
        }

        return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} updated successfully.");
    }

    public function destroyInvoice(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($invoice->status === DocumentStatus::Paid->value) {
            return redirect()->back()->with('error', 'Paid invoices cannot be deleted.');
        }

        if ($invoice->media_id && $invoice->media) {
            $invoice->media->forceDelete();
        }

        JournalEntry::where('club_id', $club->id)
            ->where('source_type', 'Invoice')
            ->where('source_id', $invoice->id)
            ->update(['status' => DocumentStatus::Void->value]);

        $this->accountingService->log($club, 'invoice', $invoice->id, 'voided', "Invoice {$invoice->invoice_number} ({$invoice->title}) deleted.");

        $invoice->delete();

        return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} deleted.");
    }

    public function editInvoice(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $members = \DB::table('club_user')
            ->join('users', 'club_user.user_id', '=', 'users.id')
            ->where('club_user.club_id', $club->id)
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]);

        return Inertia::render('Admin/Accounting/InvoiceForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'invoice' => [
                'id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'title' => $invoice->title,
                'amount' => (float) $invoice->amount,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date?->format('Y-m-d') ?? '',
                'notes' => $invoice->notes ?? '',
                'invoice_number' => $invoice->invoice_number,
                'attachment_url' => $invoice->media_id && $invoice->media ? $this->attachmentUrl($club, $invoice->media) : null,
                'created_at' => $invoice->created_at->format('d M Y'),
            ],
            'members' => $members,
        ]);
    }

    public function markInvoicePaid(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $this->accountingService->markInvoiceAsPaid($invoice, $request->user());

        return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} marked as paid.");
    }

    public function approveInvoice(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        try {
            $this->accountingService->approveInvoice($invoice, $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} approved and posted to Accounts Receivable.");
    }
}
