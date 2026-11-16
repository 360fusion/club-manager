<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Launchpad\Launchpad;
use Launchpad\Workspaces\Concerns\BelongsToWorkspace;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A file one workspace's people uploaded. Its bytes sit on the files disk under a random name; the name people see is only a label. */
class WorkspaceFile extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    protected static function booted(): void
    {
        // the bytes go with the row
        static::deleted(fn (self $file) => Storage::disk($file->disk)->delete($file->path));
    }

    /** A link that works for anyone who has it, for this long: for an image in an email, or a hand-off to another service */
    public function signedUrl(int $minutes = 60): string
    {
        return URL::temporarySignedRoute('files.signed', now()->addMinutes($minutes), ['file' => $this->getKey()]);
    }

    /** Always as an attachment, so a browser never opens what was uploaded as a page of ours */
    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path, $this->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'uploaded_by');
    }
}
