<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Launchpad\Workspaces\CurrentWorkspace;

/** "Hide this" on the Getting started card: for the person who pressed it, in this workspace only */
class OnboardingController extends Controller
{
    public function dismiss(CurrentWorkspace $current): RedirectResponse
    {
        $current->membership->update(['onboarding_dismissed_at' => now()]);

        return back();
    }
}
