<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function recurringRule()
    {
        return $this->belongsTo(RecurringRule::class);
    }

    public function agendaItems()
    {
        return $this->hasMany(AgendaItem::class)->orderBy('item_number');
    }

    public function officerAssignments()
    {
        return $this->hasMany(OfficerAssignment::class);
    }

    public function fraternalVisits()
    {
        return $this->hasMany(FraternalVisit::class);
    }

    public function rsvps()
    {
        return $this->hasMany(MeetingRsvp::class);
    }
}
