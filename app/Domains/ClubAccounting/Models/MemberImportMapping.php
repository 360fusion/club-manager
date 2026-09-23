<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lodge's remembered answer to "which column is which", found again by the shape of a file's headers.
 */
class MemberImportMapping extends Model
{
    use HasFactory;

    protected $table = 'club_acc_member_import_mappings';

    protected $fillable = ['club_id', 'name', 'header_signature', 'mapping'];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
