<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\User;
use App\Services\MasonicAnniversaries;
use App\Services\MasonicRecord;
use App\Support\ClubAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Masonic record": a member's own degrees, ranks, offices, attendance and visits in one lodge; and the lodge's
 * coming Masonic anniversaries, for the officers who mark them.
 */
class MemberRecordController extends Controller
{
    /** Who sees the lodge's anniversaries: those who look after the members, and the Master, Secretary and Almoner. */
    public static function canSeeAnniversaries(?User $user, Club $club): bool
    {
        return ClubAccess::can($user, $club, 'manage_members')
            || Member::userHoldsOffice($club, $user, [LodgeOffice::WorshipfulMaster, LodgeOffice::Secretary, LodgeOffice::Almoner]);
    }

    public function anniversaries(Request $request, string $slug, MasonicAnniversaries $anniversaries): Response
    {
        $club = $this->findClub($slug);
        abort_unless(self::canSeeAnniversaries($request->user(), $club), 403, 'Only the Master, Secretary and Almoner can see the lodge\'s anniversaries.');

        return Inertia::render('Member/Anniversaries', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'anniversaries' => $anniversaries->upcoming($club, 90),
        ]);
    }

    public function show(Request $request, string $slug, MasonicRecord $records): Response
    {
        $club = $this->findClub($slug);
        abort_unless(ClubAccess::isActiveMember($request->user(), $club), 403, 'You are not a member of this club.');

        return Inertia::render('Member/Record', [
            'club' => ['slug' => $club->slug, 'name' => $club->name, 'lodge_number' => $club->lodge_number],
            'record' => $records->for($club, $request->user()),
        ]);
    }
}
