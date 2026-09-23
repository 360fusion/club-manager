<?php

namespace App\Domains\ClubAccounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberImportRow extends Model
{
    use HasFactory;

    public const NEW = 'new';

    public const DUPLICATE = 'duplicate';

    public const POSSIBLE = 'possible_duplicate';

    public const ERROR = 'error';

    protected $table = 'club_acc_member_import_rows';

    protected $fillable = [
        'import_id', 'row_number', 'data', 'status', 'match_type', 'match_member_id', 'duplicate_of_row', 'action',
        'raw', 'errors', 'warnings', 'outcome', 'outcome_note', 'created_member_id', 'previous_values', 'applied_values',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'raw' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'previous_values' => 'array',
            'applied_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MemberImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(MemberImport::class, 'import_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function matchedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'match_member_id');
    }
}
