<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Accounting\AccountingContact;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The lists on the accounting screen that come straight from the ledger tables: journal entries, invoices, bills and
 * contacts, shaped for the page. The page reads attachments through the authorised attachment route.
 */
class LedgerScreenData
{
    /**
     * The latest journal entries with their lines, newest first.
     */
    public function journalEntries(Club $club): Collection
    {
        $journalEntries = JournalEntry::where('club_id', $club->id)
            ->with(['items.account', 'createdBy'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->take(30)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date->format('d M Y'),
                'description' => $entry->description,
                'source_type' => $entry->source_type,
                'status' => $entry->status,
                'total_debit' => $entry->total_debit,
                'formatted_total' => Currencies::format($entry->total_debit, $club),
                'created_by' => $entry->createdBy ? $entry->createdBy->name : 'System',
                'items' => $entry->items->map(fn ($item) => [
                    'id' => $item->id,
                    'account_code' => $item->account->code,
                    'account_name' => $item->account->name,
                    'debit' => $item->debit,
                    'credit' => $item->credit,
                    'memo' => $item->memo,
                ]),
            ]);

        return $journalEntries;
    }

    /**
     * The latest member invoices. `can_approve` is false for the person who raised one.
     */
    public function invoices(Club $club, User $viewer): Collection
    {
        $invoices = Invoice::where('club_id', $club->id)
            ->with(['user', 'media'])
            ->orderByDesc('created_at')
            ->take(50)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'title' => $inv->title,
                'amount' => $inv->amount,
                'formatted_amount' => Currencies::format($inv->amount, $club),
                'status' => $inv->status,
                'recipient_name' => $inv->user ? $inv->user->name : 'Member',
                'created_at' => $inv->created_at->format('d M Y'),
                'can_approve' => $inv->status === DocumentStatus::PendingApproval->value && (int) $inv->created_by !== $viewer->id,
                'paid_at' => $inv->paid_at ? $inv->paid_at->format('d M Y') : null,
                'reconciled_at' => $inv->reconciled_at ? $inv->reconciled_at->format('d M Y') : null,
                'attachment' => $inv->media ? [
                    'id' => $inv->media->id,
                    'file_name' => $inv->media->file_name,
                    'url' => $this->attachmentUrl($club, $inv->media),
                    'original_url' => $this->attachmentUrl($club, $inv->media),
                    'mime_type' => $inv->media->mime_type,
                    'size' => $inv->media->human_readable_size ?? (round($inv->media->size / 1024, 1).' KB'),
                    'is_image' => str_starts_with($inv->media->mime_type ?? '', 'image/'),
                ] : null,
            ]);

        return $invoices;
    }

    /**
     * The latest vendor bills. `can_approve` is false for the person who raised one.
     */
    public function bills(Club $club, User $viewer): Collection
    {
        $bills = Bill::where('club_id', $club->id)
            ->with('media')
            ->orderByDesc('due_date')
            ->take(50)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'bill_number' => $b->bill_number,
                'vendor_name' => $b->vendor_name,
                'category' => $b->category,
                'amount' => $b->amount,
                'formatted_amount' => Currencies::format($b->amount, $club),
                'due_date' => $b->due_date->format('d M Y'),
                'status' => $b->status,
                'notes' => $b->notes,
                'can_approve' => $b->status === DocumentStatus::PendingApproval->value && (int) $b->created_by !== $viewer->id,
                'paid_at' => $b->paid_at ? $b->paid_at->format('d M Y') : null,
                'reconciled_at' => $b->reconciled_at ? $b->reconciled_at->format('d M Y') : null,
                'attachment' => $b->media ? [
                    'id' => $b->media->id,
                    'file_name' => $b->media->file_name,
                    'url' => $this->attachmentUrl($club, $b->media),
                    'original_url' => $this->attachmentUrl($club, $b->media),
                    'mime_type' => $b->media->mime_type,
                    'size' => $b->media->human_readable_size ?? (round($b->media->size / 1024, 1).' KB'),
                    'is_image' => str_starts_with($b->media->mime_type ?? '', 'image/'),
                ] : null,
            ]);

        return $bills;
    }

    /**
     * The club's saved contacts.
     */
    public function contacts(Club $club): Collection
    {
        $contactsQuery = AccountingContact::where('club_id', $club->id)->orderBy('name')->get();

        $contacts = $contactsQuery->map(fn ($c) => [
            'id' => $c->id,
            'type' => $c->type, // person or business
            'name' => $c->name,
            'contact_person' => $c->contact_person,
            'email' => $c->email,
            'phone' => $c->phone,
            'role' => $c->role,
            'tax_id' => $c->tax_id,
            'address_line_1' => $c->address_line_1,
            'address_line_2' => $c->address_line_2,
            'city' => $c->city,
            'postcode' => $c->postcode,
            'country' => $c->country,
            'formatted_address' => implode(', ', array_filter([$c->address_line_1, $c->city, $c->postcode])),
            'notes' => $c->notes,
            'is_active' => $c->is_active,
            'created_at' => $c->created_at->format('d M Y'),
        ]);

        return $contacts;
    }

    private function attachmentUrl(Club $club, Media $media): string
    {
        return route('admin.accounting.attachments.show', ['clubSlug' => $club->slug, 'mediaId' => $media->id]);
    }
}
