<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Launchpad\Accounts\Avatar;
use Launchpad\Admin\Impersonation;
use Launchpad\Http\AccountMenu;
use Launchpad\Moderation\Reports;
use Launchpad\Notifications\Bell;
use Launchpad\Onboarding\Onboarding;
use Launchpad\Site\Announcements;
use Launchpad\Site\Consent;
use Launchpad\Site\Footer;
use Launchpad\Site\Site;
use Launchpad\Support\Locales;
use Launchpad\Workspaces\TeamAddresses;

/**
 * What every Vue page is given besides its own props: the site's name, links and footer, who is signed in with their account menu, and the one-line
 * message a redirect carried. The same sources the Blade layout reads (AccountMenu, Footer), so both draw the same menus.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();
        $links = fn (array $items) => array_map(fn ($item) => ['label' => __($item[0]), 'href' => url($item[1])], $items);

        return array_merge(parent::share($request), [
            'site' => [
                'name' => Site::name(),
                'company' => Site::get('company'),
                'tagline' => Site::get('tagline'),
                'nav' => $links((array) config('launchpad.site.nav')),
                'footer' => collect(Footer::columns())->map(fn ($items, $heading) => ['heading' => __($heading), 'links' => $links($items)])->values()->all(),
                // the page signed-in people go to to use the product, when the app has one (config launchpad.site.practice_url)
                'practiceUrl' => config('launchpad.site.practice_url'),
            ],
            'auth' => $user ? [
                'user' => ['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email, 'avatarUrl' => Avatar::url($user)],
                // the bell: the latest notices and how many are unread (null where the notifications module is off)
                'bell' => Bell::enabled($user) ? (function () use ($user) {
                    $bell = Bell::summary($user);

                    return ['unread' => $bell['unread'], 'latest' => array_map(fn ($row) => ['id' => $row['id'], 'title' => $row['title'], 'message' => $row['message'],
                        'url' => $row['url'], 'read' => $row['read'], 'at' => $row['at']?->toIso8601String()], $bell['latest'])];
                })() : null,
                'menu' => array_map(fn ($item) => ['label' => $item[0], 'href' => $item[1], 'current' => $item[2]], AccountMenu::items($user)),
                'workspaces' => AccountMenu::memberships($user)->map(fn ($m) => ['name' => $m->workspace->labelFor($user), 'slug' => $m->workspace->slug, 'current' => $m->workspace_id === $user->current_workspace_id])->values()->all(),
                'adminUrl' => AccountMenu::adminUrl($user),
            ] : null,
            // the Getting started card of the workspace the person is in (null when there is none to show)
            'onboarding' => fn () => $user ? Onboarding::forUser($user) : null,
            // what staff announced to this person (the html is Markdown rendered with raw HTML stripped)
            'announcements' => fn () => $user ? app(Announcements::class)->forUser($user)->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'html' => $a->html(), 'level' => $a->level, 'dismissible' => $a->dismissible])->all() : [],
            // where a team's pages are: '/w/{slug}', or 'https://{slug}.example.com' with team subdomains on (resources/js/team.js)
            'teamUrl' => TeamAddresses::template(),
            // what the Report button offers (empty while moderation is off)
            'reportReasons' => fn () => Route::has('reports.store') ? collect(Reports::reasons())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all() : [],
            // for the few forms that leave the app (connecting Google sends the browser to Google): an ordinary post needs the token
            'csrf' => csrf_token(),
            'flash' => ['status' => $request->session()->get('status')],
            // while staff are signed in as a customer (the web guard's person, not the default guard's)
            'impersonating' => Impersonation::active() ? ['name' => Auth::guard('web')->user()?->name, 'email' => Auth::guard('web')->user()?->email] : null,
            'consent' => ['decided' => Consent::decided($request)],
            // the language of this request and the ones to choose from (none listed when there is no choice): closures, because Inertia shares
            // before the SetLocale middleware may have run, and a closure is only read when the page is drawn
            'locale' => fn () => app()->getLocale(),
            // the sentences of the language (English sentence => translation), for the Vue pages' $t(): nothing for the default language or one without a
            // lang/<locale>.json; lazy like the two above
            'translations' => fn () => Locales::translations(),
            'locales' => fn () => count(Locales::supported()) > 1 ? collect(Locales::supported())->map(fn ($name, $code) => ['code' => (string) $code, 'name' => $name])->values()->all() : [],
        ]);
    }
}
