<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded members spreadsheet, from upload to (optionally) undo. Its rows are staged here so the review
 * step pages from the database instead of holding a whole file in the browser.
 */
class MemberImport extends Model
{
    use HasFactory;

    public const STAGED = 'staged';

    public const IMPORTED = 'imported';

    public const UNDONE = 'undone';

    public const CANCELLED = 'cancelled';

    protected $table = 'club_acc_member_imports';

    protected $fillable = [
        'club_id', 'user_id', 'filename', 'file_hash', 'stored_path', 'status', 'headers', 'mapping', 'options',
        'total_rows', 'new_count', 'duplicate_count', 'possible_count', 'error_count', 'result', 'imported_at', 'undone_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'options' => 'array',
            'result' => 'array',
            'imported_at' => 'datetime',
            'undone_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MemberImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(MemberImportRow::class, 'import_id');
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return ($this->options ?? [])[$key] ?? $default;
    }
}
