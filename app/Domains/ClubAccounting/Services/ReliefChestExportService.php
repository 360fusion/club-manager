<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\FestivalTarget;
use App\Models\Club;
use App\Support\Csv;
use App\Support\Currencies;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReliefChestExportService
{
    /**
     * Generate MCF Relief Chest CSV deposit schedule.
     */
    public function generateReliefChestCsv(Club|int $club, ?Collection $collections = null): string
    {
        $clubObj = $club instanceof Club ? $club : Club::findOrFail($club);
        $target = FestivalTarget::where('club_id', $clubObj->id)->first();
        $chestRef = $target?->relief_chest_ref ?: 'E1418';
        $provincialRef = 'L'.($clubObj->id ?: '1418').'BEN'.Carbon::now()->format('Y');

        if (! $collections) {
            $collections = CharityCollection::where('club_id', $clubObj->id)->with(['countedBy', 'witnessedBy'])->get();
        }

        $sym = trim(Currencies::symbolFor($clubObj));
        $csv = "Relief Chest Ref,Provincial Ref,Date,Collection Type,Cash Amount ({$sym}),Cheque Amount ({$sym}),Total Amount ({$sym}),Counter,Witness\n";

        foreach ($collections as $col) {
            $date = $col->created_at ? $col->created_at->format('Y-m-d') : Carbon::now()->format('Y-m-d');
            $type = $col->collection_type?->label() ?? 'Meeting Collection';
            $cash = number_format((float) $col->cash_amount, 2, '.', '');
            $cheque = number_format((float) $col->cheque_amount, 2, '.', '');
            $total = number_format((float) $col->total_amount, 2, '.', '');
            $counter = $col->countedBy?->full_name ?? 'Charity Steward';
            $witness = $col->witnessedBy?->full_name ?? 'Assistant DC';

            $csv .= Csv::line([$chestRef, $provincialRef, $date, $type, $cash, $cheque, $total, $counter, $witness]);
        }

        return $csv;
    }

    /**
     * Generate BACS export schedule for charity grant disbursements.
     */
    public function generateBacsSchedule(Club|int $club, ?Collection $grants = null): string
    {
        $clubObj = $club instanceof Club ? $club : Club::findOrFail($club);

        if (! $grants) {
            $grants = CharityGrant::where('club_id', $clubObj->id)->get();
        }

        $sym = trim(Currencies::symbolFor($clubObj));
        $csv = "BACS Ref,Recipient Name,Relief Chest Number,Purpose,Amount ({$sym}),Approval Status,Date\n";

        foreach ($grants as $g) {
            $bacsRef = $g->bacs_reference ?: 'BACS-'.($g->id ?: '001');
            $recipient = $g->recipient_name;
            $chestNo = $g->relief_chest_number ?: 'N/A';
            $purpose = $g->purpose;
            $amount = number_format((float) $g->amount, 2, '.', '');
            $status = $g->approval_status?->label() ?? 'Proposed';
            $date = $g->updated_at ? $g->updated_at->format('Y-m-d') : Carbon::now()->format('Y-m-d');

            $csv .= Csv::line([$bacsRef, $recipient, $chestNo, $purpose, $amount, $status, $date]);
        }

        return $csv;
    }
}
