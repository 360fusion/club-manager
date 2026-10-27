<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Club;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait LinksAccountingAttachments
{
    /**
     * Authorised link to an accounting attachment (stored on the private disk).
     */
    protected function attachmentUrl(Club $club, Media $media): string
    {
        return route('admin.accounting.attachments.show', ['clubSlug' => $club->slug, 'mediaId' => $media->id]);
    }
}
