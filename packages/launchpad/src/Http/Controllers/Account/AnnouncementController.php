<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Launchpad\Models\Announcement;
use Launchpad\Site\Announcements;

/** "Hide" on an announcement: for the person who pressed it; one they are not shown is not theirs to hide */
class AnnouncementController extends Controller
{
    public function dismiss(Request $request, Announcement $announcement, Announcements $announcements): RedirectResponse
    {
        abort_unless($announcements->isFor($announcement, $request->user()), 404);
        abort_unless($announcement->dismissible, 403);

        $announcements->dismiss($announcement, $request->user());

        return back();
    }
}
