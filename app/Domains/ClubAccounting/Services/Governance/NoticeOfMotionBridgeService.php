<?php

namespace App\Domains\ClubAccounting\Services\Governance;

use App\Domains\ClubAccounting\Models\ClubNoticeOfMotion;
use App\Models\AgendaItem;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NoticeOfMotionBridgeService
{
    /**
     * Export an approved Notice of Motion into a target Lodge Meeting Summons.
     */
    public function exportToSummons(ClubNoticeOfMotion $motion, Meeting $targetMeeting): bool
    {
        return DB::transaction(function () use ($motion, $targetMeeting) {
            // Construct formal summons item conforming to UGLE Rule 160 Notice of Motion requirements
            $proposerText = $motion->proposer ? $motion->proposer->name : ($motion->proposer_name ?: 'The Lodge Committee');
            $seconderText = $motion->seconder ? " (Seconded by {$motion->seconder->name})" : ($motion->seconder_name ? " (Seconded by {$motion->seconder_name})" : '');

            $itemNumber = $targetMeeting->agendaItems()->count() + 1;

            AgendaItem::create([
                'meeting_id' => $targetMeeting->id,
                'item_number' => $itemNumber,
                'title' => "NOTICE OF MOTION: {$motion->title}",
                'description' => "To be proposed by {$proposerText}{$seconderText}:\n\"{$motion->motion_text}\"".($motion->rationale ? "\n\nRationale: {$motion->rationale}" : ''),
                'presenter_user_id' => $motion->proposer_user_id,
            ]);

            $motion->update([
                'target_lodge_meeting_id' => $targetMeeting->id,
                'status' => 'published_on_summons',
                'exported_to_summons_at' => Carbon::now(),
            ]);

            return true;
        });
    }

    /**
     * Mark motion as ratified in open lodge.
     */
    public function markRatified(ClubNoticeOfMotion $motion): void
    {
        $motion->update([
            'status' => 'ratified',
            'ratified_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark motion as rejected in open lodge.
     */
    public function markRejected(ClubNoticeOfMotion $motion): void
    {
        $motion->update([
            'status' => 'rejected',
        ]);
    }
}
