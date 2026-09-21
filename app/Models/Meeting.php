<?php

namespace App\Models;

use App\Models\Accounting\MeetingFinancialReturn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'recurring_rule_id',
        'meeting_number',
        'title',
        'meeting_date',
        'starts_at',
        'rehearsal_starts_at',
        'venue',
        'dress_code',
        'status',
        'summons_published_at',
        'rsvp_cutoff_at',
        'festive_board_theme',
        'festive_board_menu',
        'dining_cost_member',
        'dining_cost_guest',
        'bank_sort_code',
        'bank_account_number',
        'payment_reference_prefix',
        'almoner_notice',
        'sick_distressed_notes',
        'postal_batch_generated_at',
        'salutation',
        'intro_text',
        'rehearsal_text',
        'honorary_members_text',
        'provincial_header_text',
        'fraternal_visits_text',
        'officers_year_label',
        'officers_roster',
        'payment_link',
        'front_page_logo',
        'front_page_title',
        'provincial_grand_master',
        'deputy_provincial_grand_master',
        'assistant_provincial_grand_masters',
        'cover_club_name',
        'cover_club_number',
        'cover_motto',
        'cover_worshipful_master',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'summons_published_at' => 'datetime',
        'rsvp_cutoff_at' => 'datetime',
        'postal_batch_generated_at' => 'datetime',
        'dining_cost_member' => 'decimal:2',
        'dining_cost_guest' => 'decimal:2',
        'officers_roster' => 'array',
    ];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<RecurringRule, $this>
     */
    public function recurringRule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class);
    }

    /**
     * @return HasMany<AgendaItem, $this>
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class)->orderBy('item_number');
    }

    /**
     * @return HasMany<OfficerAssignment, $this>
     */
    public function officerAssignments(): HasMany
    {
        return $this->hasMany(OfficerAssignment::class);
    }

    /**
     * @return HasMany<FraternalVisit, $this>
     */
    public function fraternalVisits(): HasMany
    {
        return $this->hasMany(FraternalVisit::class);
    }

    /**
     * @return HasMany<MeetingRsvp, $this>
     */
    public function rsvps(): HasMany
    {
        return $this->hasMany(MeetingRsvp::class);
    }

    /**
     * @return HasOne<MeetingFinancialReturn, $this>
     */
    public function financialReturn(): HasOne
    {
        return $this->hasOne(MeetingFinancialReturn::class);
    }
}
