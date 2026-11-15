<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somewhere a club sends its built website: an FTP(S) server (`ftps`), an SFTP server (`sftp`), cPanel through its API
 * (`cpanel`) or a GitHub repository (`github`). The config
 * holds passwords and tokens, so it is encrypted and never sent back to the browser.
 */
class SiteDeployTarget extends Model
{
    public const TYPES = ['ftps', 'sftp', 'cpanel', 'github'];

    protected $fillable = ['club_id', 'type', 'name', 'config'];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
