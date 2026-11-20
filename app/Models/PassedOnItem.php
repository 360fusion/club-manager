<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Something passed on to a lodge's members: a sister or reciprocal lodge's summons or notice, or a Provincial or
 * Grand Lodge communication (`source` manual), or a lodge on the platform's own published summons (`source`
 * platform, key facts only). Each person who gets it has a PassedOnDelivery. Sent by SendPassedOnItem.
 */
class PassedOnItem extends Model
{
    public const SUMMONS = 'summons';

    public const NOTICE = 'notice';

    public const MANUAL = 'manual';

    public const PLATFORM = 'platform';

    protected $fillable = [
        'club_id', 'sent_by', 'source', 'passed_on_by', 'kind', 'type', 'lodge_id', 'meeting_id', 'title', 'body',
        'attachment_path', 'attachment_name', 'meeting_date', 'meeting_time', 'venue', 'dress_code', 'dedupe_key',
        'recipient_count', 'email_count', 'sent_at', 'dining', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => SanitizedHtml::class,
            'meeting_date' => 'date',
            'sent_at' => 'datetime',
            'dining' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * What makes two items the same for one person: the same lodge's meeting on the same day, or the same circular
     * (the same PDF, else the same title and text) of a kind.
     */
    public static function dedupeKeyFor(string $type, ?string $kind, ?int $lodgeId, ?string $meetingDate, ?string $pdfBytes, string $title, ?string $body): string
    {
        if ($type === self::SUMMONS && $lodgeId !== null && $meetingDate !== null) {
            return 'summons:'.$lodgeId.':'.$meetingDate;
        }

        $text = $pdfBytes ?? mb_strtolower(preg_replace('/\s+/u', ' ', trim($title.' '.strip_tags((string) $body))));

        return 'circular:'.($kind ?? 'none').':'.hash('sha256', $text);
    }

    /** "Passed on by WBro A Smith, Secretary of Lodge X": their name as the lodge has it, and the office if they hold it. */
    public static function byLine(Club $club, User $sender): string
    {
        $member = Member::where('club_id', $club->id)->where('user_id', $sender->id)->first();
        $office = Member::userHoldsOffice($club, $sender, [LodgeOffice::Secretary]) ? ', Secretary of ' : ' of ';

        return 'Passed on by '.($member?->formatted_rank_name ?: $sender->name).$office.$club->name;
    }

    /** Sends the PDF inline from the private disk, under the name it was uploaded with. */
    public function streamAttachmentResponse(): StreamedResponse
    {
        abort_unless($this->attachment_path && Storage::disk('local')->exists($this->attachment_path), 404);

        return Storage::disk('local')->response($this->attachment_path, $this->attachment_name ?: 'passed-on.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<Lodge, $this> */
    public function lodge(): BelongsTo
    {
        return $this->belongsTo(Lodge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return HasMany<PassedOnDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(PassedOnDelivery::class, 'item_id');
    }
}
