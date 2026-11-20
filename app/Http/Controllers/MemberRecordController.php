<?php

namespace App\Http\Controllers;

use App\Services\MasonicRecord;
use App\Support\ClubAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Masonic record": a member's own degrees, ranks, offices, attendance and visits in one lodge.
 */
class MemberRecordController extends Controller
{
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
