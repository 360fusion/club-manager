<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A web page a lodge's details were taken from, kept so a member can check them and so a scan can
 * later tell whether the page has changed. Several lodges can share one page, such as a
 * province's list of lodges.
 */
class LodgeSource extends Model
{
    /** The lodge's own page on its province's website. */
    public const PROVINCE_PAGE = 'province_page';

    /** A page on the province's website that lists many lodges. */
    public const PROVINCE_LIST = 'province_list';

    /** A third-party directory's page for the lodge. */
    public const DIRECTORY_PAGE = 'directory_page';

    /** The Worcestershire Masonic Library and Museum catalogue's list of the units in the lodge's province. */
    public const CATALOGUE_PAGE = 'catalogue_page';

    /** UGLE's page for the meeting place the lodge uses. */
    public const UGLE_HALL = 'ugle_hall';

    public const KINDS = [
        self::PROVINCE_PAGE => 'Province website: this lodge\'s page',
        self::PROVINCE_LIST => 'Province website: list of lodges',
        self::DIRECTORY_PAGE => 'OnTheSquare directory: this lodge',
        self::CATALOGUE_PAGE => 'Masonic library catalogue: units in this province',
        self::UGLE_HALL => 'UGLE: its meeting place',
    ];

    /** The kinds that come from the province's own website: a lodge has at most one of them. */
    public const PROVINCE_KINDS = [self::PROVINCE_PAGE, self::PROVINCE_LIST];

    /** Where each kind ranks as evidence: UGLE first, then the province, then third-party directories. */
    public const TIERS = [
        self::UGLE_HALL => 1,
        self::PROVINCE_PAGE => 2,
        self::PROVINCE_LIST => 2,
        self::DIRECTORY_PAGE => 3,
        self::CATALOGUE_PAGE => 3,
    ];

    protected $fillable = ['lodge_id', 'kind', 'url', 'is_search'];

    protected function casts(): array
    {
        return [
            'is_search' => 'boolean',
            'last_checked_at' => 'datetime',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lodge, $this>
     */
    public function lodge(): BelongsTo
    {
        return $this->belongsTo(Lodge::class);
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
