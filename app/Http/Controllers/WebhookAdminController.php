<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Launchpad\Models\WebhookDelivery;
use Launchpad\Models\WebhookEndpoint;
use Launchpad\Webhooks\Webhooks;

/**
 * Where a club's events are sent: its endpoints, the signing secret (shown once), a test, and what was sent lately.
 * Launchpad sends them (the club is its team); Launchpad's own webhooks page is off here, so this is the page.
 */
class WebhookAdminController extends Controller
{
    public function index(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);
        $endpoints = WebhookEndpoint::where('workspace_id', $club->id)->orderBy('id')->get();
        $deliveries = WebhookDelivery::whereIn('endpoint_id', $endpoints->pluck('id'))->with('endpoint')->orderByDesc('id')->limit(20)->get();

        return Inertia::render('Admin/Webhooks/Index', [
            'club' => $club->only(['id', 'name', 'slug']),
            'endpoints' => $endpoints->map(fn (WebhookEndpoint $e) => ['id' => $e->id, 'url' => $e->url, 'description' => $e->description, 'events' => $e->events, 'active' => $e->active])->values(),
            'deliveries' => $deliveries->map(fn (WebhookDelivery $d) => [
                'id' => $d->id, 'url' => $d->endpoint->url, 'event' => $d->event, 'status' => $d->status, 'attempts' => $d->attempts,
                'response_code' => $d->response_code, 'last_error' => $d->last_error, 'created_at' => $d->created_at?->toIso8601String(),
            ])->values(),
            'events' => collect((array) config('launchpad.webhooks.events'))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'newSecret' => session('new_secret'),
        ]);
    }

    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', fn ($attribute, $value, $fail) => ($problem = Webhooks::urlProblem((string) $value)) ? $fail($problem) : null],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_keys((array) config('launchpad.webhooks.events')))],
        ]);

        if (WebhookEndpoint::where('workspace_id', $club->id)->count() >= 10) {
            return back()->withInput()->withErrors(['url' => 'A club can have up to 10 endpoints. Delete one first.']);
        }

        $secret = Webhooks::newSecret();
        WebhookEndpoint::create([
            'workspace_id' => $club->id, 'url' => $data['url'], 'secret' => $secret, 'events' => array_values($data['events']),
            'description' => $data['description'] ?? null, 'active' => true,
        ]);

        return redirect()->route('admin.webhooks.index', $club->slug)->with('new_secret', $secret)->with('success', 'Endpoint added. Copy the signing secret now: it will not be shown again.');
    }

    /** Switches an endpoint on or off */
    public function toggle(string $clubSlug, string $id): RedirectResponse
    {
        $endpoint = $this->endpoint($clubSlug, $id);
        $endpoint->update(['active' => ! $endpoint->active]);

        return back()->with('success', $endpoint->active ? 'Endpoint switched on.' : 'Endpoint switched off.');
    }

    public function test(string $clubSlug, string $id): RedirectResponse
    {
        $endpoint = $this->endpoint($clubSlug, $id);

        if (! $endpoint->active) {
            return back()->withErrors(['url' => 'Switch the endpoint on to send a test.']);
        }

        Webhooks::queue($endpoint, 'ping', ['message' => 'This is a test event.', 'club' => $clubSlug]);

        return back()->with('success', 'Test event queued. It shows below once it has been sent.');
    }

    public function destroy(string $clubSlug, string $id): RedirectResponse
    {
        $this->endpoint($clubSlug, $id)->delete();

        return back()->with('success', 'Endpoint deleted.');
    }

    private function endpoint(string $clubSlug, string $id): WebhookEndpoint
    {
        return WebhookEndpoint::where('workspace_id', $this->findClub($clubSlug)->id)->whereKey($id)->firstOrFail();
    }
}
