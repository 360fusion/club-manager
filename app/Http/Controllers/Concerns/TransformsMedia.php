<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\Invoice;

/**
 * How the file manager shows one file, shared by the controllers that list or change files.
 */
trait TransformsMedia
{
    /**
     * Check if a media item is linked to an Accounting Bill or Invoice, or is accounting-protected.
     */
    private function isAccountingProtected($media): bool
    {
        if ($media->getCustomProperty('is_accounting_protected') || $media->getCustomProperty('source') === 'accounting' || $media->collection_name === 'accounting') {
            return true;
        }

        return in_array($media->id, $this->mediaIdsUsedByAccounting($media->model_id), true);
    }

    /**
     * Ids of this club's files attached to a bill or an invoice, read once per request instead of once per file.
     *
     * @var array<int, list<int>>
     */
    private array $accountingMediaIds = [];

    /**
     * @return list<int>
     */
    private function mediaIdsUsedByAccounting(int $clubId): array
    {
        return $this->accountingMediaIds[$clubId] ??= Bill::where('club_id', $clubId)->whereNotNull('media_id')->pluck('media_id')
            ->merge(Invoice::where('club_id', $clubId)->whereNotNull('media_id')->pluck('media_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function privateUrl($media): string
    {
        $slug = Club::whereKey($media->model_id)->value('slug');

        return $slug ? route('admin.accounting.attachments.show', ['clubSlug' => $slug, 'mediaId' => $media->id]) : '';
    }

    private function transformMedia($media): array
    {
        $isAccountingProtected = $this->isAccountingProtected($media);

        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'human_size' => Club::formatStorageBytes($media->size),
            'collection_name' => $media->collection_name,
            'original_url' => $media->disk === 'public' ? $media->getFullUrl() : $this->privateUrl($media),
            'alt_text' => $media->getCustomProperty('alt_text', ''),
            'caption' => $media->getCustomProperty('caption', ''),
            'version_count' => $media->versions_count ?? $media->versions()->count(),
            'is_variant' => (bool) $media->getCustomProperty('is_variant', false),
            'parent_media_id' => $media->getCustomProperty('parent_media_id', null),
            'is_trashed' => $media->trashed(),
            'deleted_at' => $media->deleted_at ? $media->deleted_at->format('j M Y, H:i') : null,
            'created_at' => $media->created_at->format('j M Y, H:i'),
            'is_accounting_protected' => $isAccountingProtected,
            'accounting_bill_number' => $media->getCustomProperty('bill_number', ''),
            'accounting_invoice_number' => $media->getCustomProperty('invoice_number', ''),
        ];
    }
}
