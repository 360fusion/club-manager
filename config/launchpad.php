<?php

return [

    /*
    | The model that signs in. It uses Launchpad\Accounts\Concerns\HasLaunchpadAccount.
    */
    'user_model' => 'App\\Models\\User',

    /*
    | The site's name and look, used by every page and mail. A build sets these (or edits them in the admin, once that module is in).
    */
    'site' => [
        'name' => env('APP_NAME', 'Launchpad'),
        'accent' => '#4f46e5',

        /*
        | The colours of every page, as CSS variables, for the light and the dark scheme. Only --lp-* names are accepted. A build sets the ones it
        | wants, for example 'light' => ['--lp-accent' => '#1560c0', '--lp-on-accent' => '#ffffff'], 'dark' => ['--lp-bg' => '#0d0f1e'].
        | (Variables: --lp-accent, --lp-on-accent, --lp-bg, --lp-panel, --lp-text, --lp-muted, --lp-line, --lp-danger, --lp-ok, --lp-radius, --lp-font.)
        */
        'theme' => ['light' => [], 'dark' => []],

        /* The words the pages use as {{tokens}}. The admin can set them too (Site settings); what it sets wins over these. */
        'product' => null,
        'company' => null,
        'tagline' => null,
        'support_email' => null,
        'dpo_email' => null,
        'address' => null,
        'jurisdiction' => null,
        'effective_date' => null,
        'url' => null,

        /* The default home page at "/" (an app that has its own route for "/" wins, being registered later) */
        'home' => true,

        /* The links in the header, and which default pages are on. A page that is off answers 404. */
        'nav' => [['Features', '/features'], ['For developers', '/for-developers'], ['Pricing', '/pricing'], ['Help', '/help']],
        'pages' => [
            'about' => true, 'features' => true, 'for-developers' => true, 'for-teams' => false, 'changelog' => true,
            'terms' => true, 'privacy' => true, 'cookies' => true, 'refunds' => true, 'acceptable-use' => true,
            'dpa' => true, 'subprocessors' => true, 'accessibility' => true, 'security' => true,
        ],
        /* The footer's link columns (the Legal column is made from `legal` below). A link to a page that is off, or to a module that is off, is left out. */
        'footer' => [
            'Product' => [['Features', '/features'], ['Pricing', '/pricing'], ['For developers', '/for-developers'], ['What is new', '/changelog']],
            'Resources' => [['Help centre', '/help'], ['FAQ', '/faq'], ['Contact', '/contact']],
            'Company' => [['About', '/about'], ['Security', '/security'], ['Accessibility', '/accessibility']],
        ],
        /* The pages that are legal text: marked as templates until the admin says they have been read, and listed in the footer */
        'legal' => ['terms', 'privacy', 'cookies', 'refunds', 'acceptable-use', 'dpa', 'subprocessors', 'accessibility', 'security'],

        /* The tables the Cookie Policy and Subprocessors pages show: name, purpose, category (necessary, analytics, marketing), duration; name, purpose, location */
        'cookies' => [
            ['name' => 'laravel_session', 'purpose' => 'Keeps you signed in as you move between pages.', 'category' => 'Necessary', 'duration' => 'Until you close the browser'],
            ['name' => 'XSRF-TOKEN', 'purpose' => 'Protects forms against being sent from another site.', 'category' => 'Necessary', 'duration' => 'Until you close the browser'],
            ['name' => 'remember_web_*', 'purpose' => 'Keeps you signed in if you ticked "Keep me signed in".', 'category' => 'Necessary', 'duration' => '5 years'],
            ['name' => 'lp_consent', 'purpose' => 'Remembers your cookie choice.', 'category' => 'Necessary', 'duration' => '6 months'],
        ],
        'subprocessors' => [
            ['name' => 'Stripe', 'purpose' => 'Takes card payments and keeps card details.', 'location' => 'United States and EU'],
        ],

        /* Extra addresses for sitemap.xml: strings, or a closure returning them */
        'sitemap' => [],
    ],

    /*
    | How the package's pages are drawn: 'blade' (its own views, nothing to install) or 'inertia' (Vue pages called Launchpad/<Area>/<Page>, published by
    | `launchpad:install --vue`; needs inertiajs/inertia-laravel and its middleware).
    */
    'ui' => env('LAUNCHPAD_UI', 'blade'),

    /*
    | Where people land, and which parts of the package are switched on.
    */
    'routes' => [
        'home' => '/',
        'after_login' => '/',
    ],

    /*
    | The languages the site can be shown in, code => name as people see it.
    | A request uses the signed-in person's saved language, else the one chosen in the session, else what the browser asks for, else app.locale.
    | Only English is listed until you add more.
    */
    'locales' => ['en' => 'English'],

    /* The listed languages that are written right to left (the page gets dir="rtl") */
    'rtl_locales' => ['ar', 'he', 'fa', 'ur'],

    'modules' => [
        'auth' => false,
        'social' => false,
        'workspaces' => true,
        'billing' => false,
        'admin' => false,
        'site' => false,
        'help' => false,
        'contact' => false,
        /* The bell in the header and the Notifications page */
        'notifications' => false,
        /* Off until an app has a separate service to hand a signed token to */
        'service_token' => false,
        /* Off until there is a service to tell: every change to people, workspaces, roles and plans is then sent to it, signed */
        'sync' => false,
        /* Customers' API tokens (the API page and /api/v1) and outbound webhooks (the Webhooks page) */
        'api' => false,
        'webhooks' => true,
        /* The Files page: uploads that belong to a workspace */
        'files' => true,
        /* A team signs its people in with its company's identity provider (OpenID Connect), set up by the team's owner */
        'sso' => false,
        /* A team's directory adds and removes its people (SCIM 2.0), once its owner makes a token on the single sign-on page */
        'scim' => false,
        /* Sign in with a passkey (also needs the User to implement HasPasskeys), and with a link sent by email */
        'passkeys' => false,
        'login_links' => false,
        /* An MCP server at /mcp for customers' AI assistants, signed in with the team's API keys (needs api) */
        'mcp' => false,
        /* Reports of content people think breaks the rules, and HQ's queue for them: off until the app has content to report (moderation.types) */
        'moderation' => false,
        /* A blog at /blog (with a feed and sitemap entries), written by staff in HQ */
        'blog' => false,
        /* A public roadmap at /roadmap: people suggest ideas (shown once staff approve them) and vote */
        'roadmap' => false,
        /* Sign-up closed: people join a waitlist at /waitlist and staff let them in from HQ (team invitations still work) */
        'waitlist' => false,
        /* The Getting started card (config launchpad.onboarding) */
        'onboarding' => false,
    ],

    'workspaces' => [
        /* Everyone gets a workspace of their own at sign-up */
        'personal_on_signup' => false,
        // a club is the team: Launchpad's back end (activity log, webhooks, files, flags) reads clubs, and club-manager keeps its own pages
        'table' => 'clubs',
        'pages' => false,
        /* Team addresses on subdomains ({slug}.example.com) instead of /w/{slug}: the domain they go under, then staff turn it on in HQ (Team
           addresses). It also needs SESSION_DOMAIN=.example.com and a wildcard DNS record. `subdomains` true or false here overrides HQ. */
        'subdomain_domain' => env('LAUNCHPAD_TEAM_DOMAIN'),
        'subdomains' => env('LAUNCHPAD_TEAM_SUBDOMAINS') === null ? null : (bool) env('LAUNCHPAD_TEAM_SUBDOMAINS'),

        /* How many roles of its own a team can make (on top of owner, admin, manager and member) */
        'max_custom_roles' => 20,

        /* What an owner or admin can tick for each person, on top of their role: key => [label, what it lets them do, the roles that have it unticked].
           An app adds its own (and checks them with $membership->can('key') or the route middleware `workspace:key`). */
        'permissions' => [
            'manage-billing' => ['Manage billing', 'See and change the plan, card and invoices.', ['owner', 'admin']],
            'manage-members' => ['Manage people', 'Invite people, change their role and permissions, and remove them.', ['owner', 'admin']],
        ],
    ],

    /* Sign in with Google, Facebook, Microsoft: a button shows only for a provider that has keys. Facebook is off unless FACEBOOK_LOGIN=true. */
    'social' => [
        'google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET')],
        'facebook' => ['client_id' => env('FACEBOOK_CLIENT_ID'), 'client_secret' => env('FACEBOOK_CLIENT_SECRET'), 'login' => (bool) env('FACEBOOK_LOGIN', false)],
        'azure' => ['client_id' => env('AZURE_CLIENT_ID'), 'client_secret' => env('AZURE_CLIENT_SECRET'), 'tenant' => env('AZURE_TENANT_ID', 'common')],
    ],

    'signup' => [
        /*
        | Ask the address's domain whether it can receive mail ("email:rfc,dns"). It catches a typo that makes up a domain, and costs a lookup,
        | so it is on for a live site and off on a laptop and in tests.
        */
        'dns_check' => (bool) env('SIGNUP_DNS_CHECK', env('APP_ENV') === 'production'),

        /*
        | Domains that give out throwaway inboxes: an address on one is refused at sign-up. Add more, comma separated, in SIGNUP_BLOCKED_DOMAINS.
        */
        'blocked_domains' => array_values(array_unique(array_merge([
            '10minutemail.com', '10minutemail.net', 'dispostable.com', 'emailondeck.com', 'fakeinbox.com', 'getairmail.com',
            'getnada.com', 'guerrillamail.biz', 'guerrillamail.com', 'guerrillamail.de', 'guerrillamail.info', 'guerrillamail.net',
            'guerrillamail.org', 'guerrillamailblock.com', 'maildrop.cc', 'mailinator.com', 'mailnesia.com', 'mintemail.com',
            'mohmal.com', 'moakt.com', 'sharklasers.com', 'spam4.me', 'spamgourmet.com', 'temp-mail.org', 'tempinbox.com',
            'tempmail.com', 'tempmail.net', 'throwawaymail.com', 'trashmail.com', 'trashmail.net', 'yopmail.com', 'yopmail.fr',
        ], array_filter(array_map(fn ($domain) => strtolower(trim($domain)), explode(',', (string) env('SIGNUP_BLOCKED_DOMAINS', ''))))))),

        /*
        | Country endings (mail.ru, ...) refused at sign-up. Empty by default: whether to block a country is a decision for each build.
        | Add some, comma separated, in SIGNUP_BLOCKED_COUNTRY_DOMAINS.
        */
        'blocked_country_domains' => array_values(array_filter(array_map(
            fn ($tld) => strtolower(trim($tld, " .\t\n")),
            explode(',', (string) env('SIGNUP_BLOCKED_COUNTRY_DOMAINS', ''))
        ))),

        'blocked_country_message' => 'We can\'t accept email addresses on this domain. Please use another address.',
    ],

    'verification' => [
        /* 'off' is honoured only in the local and testing environments */
        'mode_override' => env('EMAIL_VERIFICATION'),

        /* Days an account that was there, unverified, when verification became required keeps working */
        'grace_days' => 14,

        /* What an unverified person can still reach, besides the verification routes: fix a mistyped address, leave */
        'allowed_routes' => [
            'verification.notice', 'verification.send', 'verification.status', 'verification.verify', 'logout', 'password.confirm', 'password.confirm.store',
            'account.security', 'password.update', 'two-factor.*', 'account.privacy', 'account.export', 'account.close',
        ],
        'allowed_paths' => ['confirm-password'],
    ],

    'billing' => [
        'currency' => env('CASHIER_CURRENCY', 'gbp'),

        /* How amounts are written on the pages (£15.00 for en_GB). Null follows the person's language. */
        'locale' => env('LAUNCHPAD_LOCALE'),

        /* Stripe Tax works out VAT and collects the address and tax number it needs (docs/stripe-setup.md) */
        'automatic_tax' => (bool) env('LAUNCHPAD_AUTOMATIC_TAX', false),

        'subscription_type' => 'default',

        /* The plan of a workspace with no valid subscription */
        'free_plan' => 'free',

        /*
        | The plans, cheapest first (the order is the rank: a later plan is a step up). Prices are in the smallest currency unit; the ids are Stripe
        | Price ids from the account's own catalogue. A build replaces this whole list. `limits` of 999999 mean no limit.
        */
        'plans' => [
            'free' => [
                'name' => 'Free',
                'description' => 'For trying it out.',
                'features' => [],
                'limits' => ['seats' => 1],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'For one person who uses it every day.',
                'prices' => [
                    'monthly' => ['id' => env('STRIPE_PRICE_PRO_MONTHLY'), 'amount' => 1500],
                    'annual' => ['id' => env('STRIPE_PRICE_PRO_ANNUAL'), 'amount' => 14400],
                ],
                /* Days free before the first payment (a workspace gets one trial, on its first subscription). 0 or leave out for none. */
                'trial_days' => 14,
                'features' => ['pro'],
                'limits' => ['seats' => 1],
            ],
            'team' => [
                'name' => 'Team',
                'description' => 'For a group, priced per person.',
                'per_seat' => true,
                /* the fewest seats it can be bought for */
                'min_seats' => 1,
                'prices' => [
                    'monthly' => ['id' => env('STRIPE_PRICE_TEAM_MONTHLY'), 'amount' => 4200],
                    'annual' => ['id' => env('STRIPE_PRICE_TEAM_ANNUAL'), 'amount' => 40200],
                ],
                'features' => ['pro', 'team'],
                'limits' => ['seats' => 1],
            ],
        ],
    ],

    /* HQ: the staff area that runs the whole platform (every account, team and payment). Not a customer's own admin, which an app is free to call "admin". */
    'admin' => [
        'path' => env('LAUNCHPAD_HQ_PATH', env('LAUNCHPAD_ADMIN_PATH', 'hq')),
        'name' => env('LAUNCHPAD_HQ_NAME'), // the name it shows, e.g. "AcmeHQ"; empty: the site's name and "HQ"
        'record_errors' => env('LAUNCHPAD_RECORD_ERRORS', true), // every reported error also goes to HQ's Errors page
        'timezone' => env('LAUNCHPAD_ADMIN_TIMEZONE'),
        /* What each member of staff can do in the admin: key => [label, what it lets them do, the staff roles that have it unless ticked otherwise].
           An admin changes a person's ticks on the Staff page. An app adds its own and checks them with StaffAccess::allowed('key'). */
        'permissions' => [
            'view-accounts' => ['View accounts', 'See accounts, workspaces and who is closing.', ['admin', 'support']],
            'manage-accounts' => ['Manage accounts', 'Edit an account, mark its email verified, send a password reset.', ['admin', 'support']],
            'close-accounts' => ['Close accounts', 'Close and restore accounts.', ['admin']],
            'view-billing' => ['View billing', 'See subscriptions and Stripe events.', ['admin', 'support']],
            'manage-billing' => ['Manage billing', 'Change plan limits.', ['admin']],
            'manage-messages' => ['Handle messages', 'Read and answer contact messages and help feedback.', ['admin', 'support']],
            'manage-features' => ['Manage features', 'Switch features on and off, for everyone or for a workspace.', ['admin']],
            'manage-site' => ['Manage the website', 'Site settings and website pages.', ['admin']],
            'moderate' => ['Moderate', 'Review what people report and mark it acted on or dismissed.', ['admin', 'support']],
            'view-logs' => ['View logs', 'The admin activity log, sync events and system health.', ['admin']],
            'impersonate' => ['Sign in as a customer', 'Open the site as a customer, to see what they see and help them.', ['admin']],
            'manage-staff' => ['Manage staff', 'Add and remove staff, and change their role and permissions.', ['admin']],
        ],

        /* Staff are asked for a code from an authenticator app unless this is false AND APP_ENV is "local" (both are needed) */
        'require_two_factor' => env('LAUNCHPAD_ADMIN_2FA', true),
    ],

    /*
    | A short-lived signed token for another service (an API the app does not run itself): who is asking, in which workspace, with what role and
    | what plan limits. Both sides hold the secret (32 characters or more). See docs/service-token.md.
    */
    'service_token' => [
        'secret' => env('LAUNCHPAD_SERVICE_TOKEN_SECRET'),
        'ttl' => 300,
        'issuer' => env('LAUNCHPAD_SERVICE_ISSUER'),
        'audience' => env('LAUNCHPAD_SERVICE_AUDIENCE', 'service'),
    ],

    /* Staff signing in as a customer (the `impersonate` staff permission). `blocked` are route names (wildcards allowed) refused while they do. */
    'impersonation' => [
        'minutes' => 60,
        'blocked' => ['password.*', 'two-factor.*', 'account.close', 'account.export', 'account.connections.*', 'account.sessions.*', 'account.profile.update', 'account.avatar.*', 'workspace.destroy', 'workspace.transfer', 'workspace.leave', 'notifications.*'],
    ],

    'sync' => [
        'url' => env('LAUNCHPAD_SYNC_URL'),
        'secret' => env('LAUNCHPAD_SYNC_SECRET'),
        'timeout' => 5,
        'max_attempts' => 12,
    ],

    /* The MCP server's own tools are the team, its people and its usage. Add yours: classes extending Laravel\Mcp\Server\Tool (docs/api-and-webhooks.md) */
    'mcp' => [
        'tools' => [],
        /* A plan feature (launchpad.billing.plans.*.features) a team's plan must have for AI access at all; null: every team */
        'feature' => null,
        /* tool name => a plan feature it needs as well, e.g. 'list-members' => 'team'. Staff switch tools off on HQ's AI access page. */
        'tool_features' => [],
    ],

    'moderation' => [
        /* key => a model implementing Launchpad\Moderation\Reportable, e.g. 'post' => App\Models\Post::class. The key is what is stored. */
        'types' => [],
        /* key => what people choose from (translated) */
        'reasons' => [
            'spam' => 'Spam or advertising',
            'abuse' => 'Harassment or abuse',
            'illegal' => 'Illegal content',
            'other' => 'Something else',
        ],
    ],

    'api' => [
        /* What a token can be given: key => label. A route asks for one with the middleware `launchpad.api:key`. `*` on a token means all of them. */
        'abilities' => [
            'read' => 'Read: see the workspace and its people',
            'write' => 'Write: change things (for the routes an app adds)',
        ],
        /* What every token starts with, so a leaked one is easy to recognise (Sanctum's token_prefix, unless the app has set that) */
        'token_prefix' => 'lp_',
        /* Requests a minute for one token */
        'rate_limit' => 60,
        /* Days the daily call counts of a token are kept */
        'usage_days' => 90,
    ],

    'webhooks' => [
        /* The events a customer can subscribe to: key => label. An app adds its own and calls Launchpad\Webhooks\Webhooks::dispatch($workspace, 'key', [...]). */
        'events' => [
            'member.added' => 'A member joined the club',
            'member.removed' => 'A member was removed from the club',
            'member.role_changed' => 'A member\'s role changed',
            'member.status_changed' => 'A member\'s status changed',
        ],
        /* Tries before a delivery is given up on (the wait grows from 30 seconds to an hour) */
        'max_attempts' => 8,
    ],

    /*
    | Features to switch on and off. key => label, and any of: `on` (true: on for everyone), `plans` (on for workspaces on these plans), `rollout`
    | (on for this percentage of workspaces). They are Laravel Pennant features scoped to the workspace: Feature::active('key'), @feature('key') in a view, or
    | the route middleware `feature:key`. Staff override each in the admin (Features), for everyone or for one workspace. A workspace is worked out once and
    | kept: after changing a flag here, run `php artisan pennant:purge key`.
    | 'new-editor' => ['label' => 'The new editor', 'plans' => ['pro'], 'rollout' => 10],
    */
    'features' => [],
    // club-manager's own flags keep Pennant's default scope; Launchpad's are asked for with Feature::for($club->asTeam())
    'features_default_scope' => false,

    'onboarding' => [
        /* The built-in steps of the Getting started card, in order: invite, plan, api, files. An app adds its own with Launchpad\Onboarding\Onboarding::step(). */
        'steps' => ['invite', 'plan'],
    ],

    'sso' => [
        /* A plan feature (billing.plans.*.features) a team needs to set up single sign-on; null lets every team's owner do it */
        'feature' => null,
    ],

    'metering' => [
        /*
        | What is counted per team: key => label and `resets` (monthly, on the 1st, or never). A plan sets a quota with a limit of the same key
        | (billing.plans.*.limits); without one the meter only counts. Count with the route middleware `meter:key` or Launchpad\Metering\Usage::record().
        */
        'meters' => [
            'api_calls' => ['label' => 'API calls', 'resets' => 'monthly'],
        ],
    ],

    'files' => [
        /* Private by default: nothing is served from the disk's own address, only through the Files page or a signed link */
        'disk' => env('LAUNCHPAD_FILES_DISK', 'local'),
        'max_kb' => 10240,
        /* Checked against what the file is, not what it is called. No html or svg: those would run as a page of ours if ever opened. */
        'mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'docx', 'xlsx'],
        /* What a workspace may keep, in MB; a plan with a `storage_mb` limit (billing.plans.*.limits) uses that instead */
        'quota_mb' => 100,
    ],

    'contact' => [
        /* The topic list on the contact form (empty hides it) */
        'topics' => ['General question', 'Billing', 'My account', 'Report a problem'],
    ],

    'accounts' => [
        /* Days a closed account is kept, and can be restored, before launchpad:purge-closed removes it */
        'retention_days' => 30,

        /* Where uploaded profile photos are kept (a disk in config/filesystems.php that the web can reach: `public` needs php artisan storage:link) */
        'avatar_disk' => env('LAUNCHPAD_AVATAR_DISK', 'public'),

        /* The emails a person can switch off on their Notifications page: key => [label, what it is, on by default]. Security emails are always sent.
           Launchpad sends none of these itself: the app asks NotificationPreferences::wants($user, 'key') before it sends one. */
        'notifications' => [
            'product_news' => ['Product news', 'Now and then, what is new and how to get more from the product.', true],
        ],
    ],

    'legal' => [
        /* The version of the Terms a person agrees to by signing up; recorded on the account */
        'terms_version' => env('TERMS_VERSION', '1'),
    ],

    'turnstile' => [
        'key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
        /* Unset means on whenever both keys are present */
        'enabled' => env('TURNSTILE_ENABLED'),
    ],
];
