<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\WebhookDelivery;
use Launchpad\Models\WebhookEndpoint;
use Launchpad\Webhooks\Webhooks;
use Launchpad\Workspaces\CurrentWorkspace;

/** The Webhooks page of a workspace: where to send its events, the signing secret (shown once), a test, and what was sent lately */
class WebhookController extends Controller
{
    public function index(CurrentWorkspace $current): View|Responsable
    {
        $workspace = $current->workspace;
        $endpoints = WebhookEndpoint::where('workspace_id', $workspace->id)->orderBy('id')->get();
        $deliveries = WebhookDelivery::whereIn('endpoint_id', $endpoints->pluck('id'))->with('endpoint')->orderByDesc('id')->limit(20)->get();
        $events = (array) config('launchpad.webhooks.events');
        $newSecret = session('new_secret');

        return Ui::render('workspaces.webhooks', [
            'workspace' => $workspace, 'endpoints' => $endpoints, 'deliveries' => $deliveries, 'events' => $events, 'newSecret' => $newSecret,
        ], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'endpoints' => $endpoints->map(fn (WebhookEndpoint $e) => ['id' => $e->id, 'url' => $e->url, 'description' => $e->description, 'events' => $e->events, 'active' => $e->active])->values(),
            'deliveries' => $deliveries->map(fn (WebhookDelivery $d) => [
                'id' => $d->id, 'url' => $d->endpoint->url, 'event' => $d->event, 'status' => $d->status, 'attempts' => $d->attempts,
                'response_code' => $d->response_code, 'last_error' => $d->last_error, 'created_at' => $d->created_at,
            ])->values(),
            'events' => collect($events)->map(fn ($label, $key) => ['key' => $key, 'label' => __($label)])->values(),
            'newSecret' => $newSecret,
        ]);
    }

    public function store(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', fn ($attribute, $value, $fail) => ($problem = Webhooks::urlProblem((string) $value)) ? $fail($problem) : null],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_keys((array) config('launchpad.webhooks.events')))],
        ]);

        if (WebhookEndpoint::where('workspace_id', $current->workspace->id)->count() >= 10) {
            return back()->withInput()->withErrors(['url' => __('A team can have up to 10 endpoints. Delete one first.')]);
        }

        $secret = Webhooks::newSecret();
        WebhookEndpoint::create([
            'workspace_id' => $current->workspace->id, 'url' => $data['url'], 'secret' => $secret, 'events' => array_values($data['events']),
            'description' => $data['description'] ?? null, 'active' => true,
        ]);

        return redirect()->route('workspace.webhooks', $current->workspace->slug)->with('new_secret', $secret)->with('status', __('Endpoint added. Copy the signing secret now: it will not be shown again.'));
    }

    /** Switches an endpoint on or off */
    public function update(string $slug, CurrentWorkspace $current, string $endpoint): RedirectResponse
    {
        $endpoint = $this->find($current, $endpoint);
        $endpoint->update(['active' => ! $endpoint->active]);

        return back()->with('status', $endpoint->active ? __('Endpoint switched on.') : __('Endpoint switched off.'));
    }

    public function destroy(string $slug, CurrentWorkspace $current, string $endpoint): RedirectResponse
    {
        $this->find($current, $endpoint)->delete();

        return back()->with('status', __('Endpoint deleted.'));
    }

    public function test(string $slug, CurrentWorkspace $current, string $endpoint): RedirectResponse
    {
        $endpoint = $this->find($current, $endpoint);

        if (! $endpoint->active) {
            return back()->withErrors(['url' => __('Switch the endpoint on to send a test.')]);
        }

        Webhooks::queue($endpoint, 'ping', ['message' => 'This is a test event.', 'workspace' => $current->workspace->slug]);

        return back()->with('status', __('Test event queued. It shows below once it has been sent.'));
    }

    private function find(CurrentWorkspace $current, string $id): WebhookEndpoint
    {
        return WebhookEndpoint::where('workspace_id', $current->workspace->id)->whereKey($id)->firstOrFail();
    }
}
