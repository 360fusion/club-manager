<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubMemberStatus;
use App\Http\Requests\MeetingAdmin\GenerateSeasonRequest;
use App\Models\Meeting;
use App\Models\RecurringRule;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use App\Services\MeetingScheduleService;
use App\Services\SummonsSender;
use App\Support\NodeBinaries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * A meeting's summons: the printed PDF, publishing it to members and generating a season of meetings. Split out of MeetingAdminController; the URLs, route names and permissions are unchanged.
 */
class MeetingSummonsController extends Controller
{
    /**
     * Render 2-page printable HTML/PDF summons view.
     */
    public function pdf(string $clubSlug, int $id)
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.officerRole', 'officerAssignments.user'])
            ->firstOrFail();

        $members = $club->users()->wherePivot('role', '!=', 'visitor')->wherePivot('status', ClubMemberStatus::Active->value)->get();
        // No stand-ins: a summons must not name someone else as Secretary or Master when the lodge has not said who they are.
        $secretaryUser = ($secretary = Member::summonsSecretary($club)) ? (object) $secretary : null;
        $worshipfulMaster = Member::holdingOffice($club, LodgeOffice::WorshipfulMaster);

        $charityGrants = CharityGrant::where('club_id', $club->id)
            ->forSummons($meeting)
            ->with(['proposer', 'seconder'])
            ->get();

        $viewData = [
            'club' => $club,
            'meeting' => $meeting,
            'members' => $members,
            'officerAssignments' => $meeting->officerAssignments,
            'secretaryUser' => $secretaryUser,
            'worshipfulMaster' => $worshipfulMaster,
            'charityGrants' => $charityGrants,
        ];

        if (request()->has('download')) {
            $filename = 'Summons-'.Str::slug($club->name).'-'.$meeting->meeting_date->format('Y-m-d').'.pdf';

            ['node' => $nodeBinary, 'npm' => $npmBinary] = NodeBinaries::find();

            try {
                return Pdf::view('summons.pdf', $viewData)
                    ->landscape()
                    ->withBrowsershot(function ($browsershot) use ($nodeBinary, $npmBinary) {
                        if ($nodeBinary && file_exists($nodeBinary)) {
                            $browsershot->setNodeBinary($nodeBinary);
                            $binDir = str_replace(' ', '\ ', dirname($nodeBinary));
                            $browsershot->setIncludePath($binDir.':/opt/homebrew/bin:/usr/local/bin:/usr/bin');
                        }
                        if ($npmBinary && file_exists($npmBinary)) {
                            $browsershot->setNpmBinary($npmBinary);
                        }
                    })
                    ->name($filename)
                    ->toResponse(request());
            } catch (\Throwable $e) {
                // The PDF is only rendered by toResponse(), so it must run inside this try for the fallback to work.
                // Say so in the log: without it a missing Chrome for Puppeteer quietly changes how every PDF looks.
                report($e);

                return \Barryvdh\DomPDF\Facade\Pdf::loadView('summons.pdf', $viewData)
                    ->setPaper('a4', 'landscape')
                    ->download($filename);
            }
        }

        return view('summons.pdf', $viewData);
    }

    /**
     * Publish summons & issue passwordless tokens to members.
     */
    public function publishSummons(string $clubSlug, int $id, SummonsSender $summons): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        $alreadyPublished = $meeting->status === 'published';

        $meeting->update([
            'status' => 'published',
            'summons_published_at' => Carbon::now(),
        ]);

        // Only people who have not had it yet are emailed: links already sent keep working, and anyone who joined since gets one.
        $sent = $summons->sendToNewRecipients($club, $meeting);

        if ($alreadyPublished) {
            return redirect()->back()->with('success', $sent
                ? "Summons updated and emailed to {$sent} ".Str::plural('person', $sent).' who had not had it yet.'
                : 'Summons updated. Everyone has already been sent it, and their RSVP links still work.');
        }

        app(ClubNotifier::class)->toMembers($club, ClubNotification::summons($meeting, $club), auth()->user());
        app(ClubNotifier::class)->toFollowers($club, $meeting);

        return redirect()->back()->with('success', "Summons published and emailed to {$sent} ".Str::plural('person', $sent).', each with their own RSVP link.');
    }

    /**
     * Batch generate season meetings using recurring rule engine.
     */
    public function generateSeason(GenerateSeasonRequest $request, string $clubSlug, MeetingScheduleService $scheduleService): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $startTime = $request->starts_at ?: '18:30';
        $rehearsalTime = $request->rehearsal_starts_at ?: '17:30';

        // New meetings copy the lodge's own last meeting (venue, dress, dining prices); a lodge with none starts from its hall.
        $previous = Meeting::where('club_id', $club->id)->orderByDesc('meeting_date')->first();
        $venue = $previous->venue ?? $club->defaultMeetingVenue();
        $dressCode = $previous->dress_code ?? 'Dark Suit, Craft Regalia';

        $rule = RecurringRule::create([
            'club_id' => $club->id,
            'name' => "Regular Meetings ({$request->year})",
            'occurrence' => $request->occurrence,
            'day_of_week' => $request->day_of_week,
            'active_months' => $request->active_months,
            'default_start_time' => $startTime,
            'default_rehearsal_time' => $rehearsalTime,
            'default_venue' => $venue ?: null,
            'default_dress_code' => $dressCode,
        ]);

        $dates = $scheduleService->generateSeasonDates(
            (int) $request->year,
            $request->active_months,
            $request->occurrence,
            $request->day_of_week
        );

        $count = 0;
        foreach ($dates as $index => $date) {
            $meeting = Meeting::firstOrCreate(
                [
                    'club_id' => $club->id,
                    'meeting_date' => $date->format('Y-m-d'),
                ],
                [
                    'recurring_rule_id' => $rule->id,
                    'meeting_number' => null,
                    'title' => 'Meeting - '.$date->format('jS F Y'),
                    'starts_at' => $startTime,
                    'rehearsal_starts_at' => $rehearsalTime,
                    'venue' => $venue,
                    'dress_code' => $dressCode,
                    'dining_cost_member' => $previous->dining_cost_member ?? 0,
                    'dining_cost_guest' => $previous->dining_cost_guest ?? 0,
                    'bank_sort_code' => $club->settings['bank_sort_code'] ?? null,
                    'bank_account_number' => $club->settings['bank_account_number'] ?? null,
                    'payment_reference_prefix' => 'SUMMONS',
                    'rsvp_cutoff_at' => $date->copy()->subDays(5)->endOfDay(),
                    'status' => 'draft',
                ]
            );
            $count += $meeting->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->back()->with('success', "Added {$count} ".Str::plural('meeting', $count)." for {$request->year}; dates that already had a meeting were left as they were.");
    }
}
