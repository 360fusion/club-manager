@verbatim
## Launchpad

Launchpad is the base this app is built on: accounts, teams ("workspaces" in code, "teams" in anything a person reads), Stripe billing, a Filament admin, a team API and webhooks, files, feature flags and a website. Use what it has before writing your own, and read `vendor/360fusion/launchpad/docs/` (start with `building-on-launchpad.md`).

### Rules that keep one team's data away from another

- A table of a team's own rows is made with `$table->belongsToWorkspace()` and its model uses `Launchpad\Workspaces\Concerns\BelongsToWorkspace`. Never filter by team by hand or take a team id from the request.
- Team pages live under `/w/{slug}/...` behind the `workspace` middleware (`workspace:manage-members` to need a permission). The current team is `app(Launchpad\Workspaces\CurrentWorkspace::class)->workspace`.
- Every route needs `auth`, `guest`, `signed`, `workspace`, `launchpad.api` or `launchpad.public`. Use the `AssertsRoutesAreClassified` test trait.
- API routes for customers use `launchpad.api` (Laravel Sanctum tokens made for one team); they never read the team from the URL.

### Wording

- Wrap every sentence a person reads in `__()` with the whole English sentence as the key, and say "team", never "workspace". A sentence with a link or emphasis goes through `Launchpad\Support\Trans::html()` (Vue: `$t()` and `<Trans k="...">`). See `vendor/360fusion/launchpad/docs/translations.md`.

### Use these, do not rebuild them

<code-snippet name="Launchpad building blocks" lang="php">
// plans and limits
app(Launchpad\Billing\Entitlements::class)->allows($workspace, 'feature');
app(Launchpad\Billing\Entitlements::class)->within($workspace, 'seats', $count);

// uploads (private disk, type and size checks, per-team quota)
app(Launchpad\Files\Files::class)->store($workspace, $request->file('file'), $request->user());

// feature flags: list them in config launchpad.features; Laravel Pennant, scoped to the current team
Laravel\Pennant\Feature::active('new-editor');
Laravel\Pennant\Feature::for($workspace)->active('new-editor'); // with features_default_scope off

// a line on the Team activity page (Launchpad writes the usual ones itself)
Launchpad\Workspaces\Activity::record($workspace, 'project.created', 'created the project Apollo');

// tell a team's webhook endpoints (add the event to config launchpad.webhooks.events)
Launchpad\Webhooks\Webhooks::dispatch($workspace, 'project.created', ['id' => $project->id]);

// a step on the Getting started card
Launchpad\Onboarding\Onboarding::step('first-project', 'Make your first project', fn ($w) => route('projects.create', $w->slug), fn ($w) => $w->projects()->exists(), 'manage-workspace');

// a mail staff can edit in the admin
Launchpad\Mail\Templates::register(key: 'app.welcome', label: 'Welcome', subject: 'Welcome, {{name}}', body: 'Hello {{name}}', variables: ['name']);
</code-snippet>

After changing a feature flag's rules in config, run `php artisan pennant:purge <flag>`: Pennant keeps each team's answer.
@endverbatim
