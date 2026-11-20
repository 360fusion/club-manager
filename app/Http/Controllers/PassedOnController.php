<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Lodge;
use App\Models\PassedOnDelivery;
use App\Models\User;
use App\Support\PassedOnPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The member's side of passed-on items: opening one (signed in, or from the email by its private token, which needs
 * no account), its PDF, muting its lodge, and their choices of what reaches them and how.
 */
class PassedOnController extends Controller
{
    public function show(Request $request, int $delivery): Response
    {
        $copy = $this->mine($request, $delivery);
        $copy->read_at ??= now();
        $copy->save();
        $item = $copy->item;
        $prefs = PassedOnPreferences::fromArray($request->user()->passed_on_prefs);

        return Inertia::render('Members/PassedOn/Show', [
            'item' => [
                'id' => $copy->id,
                'title' => $item->title,
                'kind' => PassedOnPreferences::LABELS[$copy->kind] ?? '',
                'passed_on_by' => $item->passed_on_by,
                'body' => $item->body,
                'meeting' => $item->meeting_date ? [
                    'date' => $item->meeting_date->format('l j F Y'),
                    'time' => $item->meeting_time,
                    'venue' => $item->venue,
                    'dress_code' => $item->dress_code,
                ] : null,
                'attachment_url' => $item->attachment_path ? route('members.passed_on.attachment', ['delivery' => $copy->id]) : null,
                'attachment_name' => $item->attachment_name,
                'lodge' => $item->lodge ? ['name' => $item->lodge->displayName(), 'url' => $item->lodge->status === 'active' ? route('lodges.show', ['slug' => $item->lodge->slug]) : null, 'muted' => $prefs->mutes($item->lodge_id)] : null,
            ],
        ]);
    }

    public function attachment(Request $request, int $delivery): StreamedResponse
    {
        return $this->mine($request, $delivery)->item->streamAttachmentResponse();
    }

    public function mute(Request $request, int $delivery): RedirectResponse
    {
        $copy = $this->mine($request, $delivery);
        $lodge = $copy->item->lodge;
        abort_unless($lodge !== null, 404);
        $muted = $request->boolean('muted', true);

        $this->save($request->user(), $this->prefsFor($request->user())->withMuted($lodge->id, $muted));

        return back()->with('success', $muted ? 'You will not hear about '.$lodge->displayName().' again.' : 'You will hear about '.$lodge->displayName().' again.');
    }

    public function choices(Request $request): Response
    {
        $prefs = $this->prefsFor($request->user());

        return Inertia::render('Members/PassedOnChoices', [
            'kinds' => collect(PassedOnPreferences::LABELS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label, 'channels' => $prefs->channels($key)])->values(),
            'muted' => Lodge::whereIn('id', $prefs->mutedLodges())->with('clubType:id,code,name')->orderBy('name')->get()
                ->map(fn (Lodge $lodge) => ['id' => $lodge->id, 'name' => $lodge->displayName()])->values(),
            'calendar' => $prefs->calendar(),
        ]);
    }

    public function updateChoices(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kinds' => ['sometimes', 'array'],
            'kinds.*' => ['array'],
            'kinds.*.*' => [Rule::in(PassedOnPreferences::CHANNELS)],
            'calendar' => ['sometimes', 'boolean'],
            'unmute' => ['sometimes', 'integer'],
        ]);

        $prefs = $this->prefsFor($request->user());
        foreach ($validated['kinds'] ?? [] as $kind => $channels) {
            $prefs = $prefs->withChannels((string) $kind, $channels);
        }
        if (array_key_exists('calendar', $validated)) {
            $prefs = $prefs->withCalendar((bool) $validated['calendar']);
        }
        if (isset($validated['unmute'])) {
            $prefs = $prefs->withMuted((int) $validated['unmute'], false);
        }

        $this->save($request->user(), $prefs);

        return back()->with('success', 'Your choices are saved.');
    }

    /** The item from the email, by its private token: no sign-in, and for members with no account too. */
    public function publicShow(string $token): HttpResponse
    {
        $copy = $this->byToken($token);
        $copy->read_at ??= now();
        $copy->save();

        return response()->view('passed-on.public', $this->publicData($copy));
    }

    public function publicAttachment(string $token): StreamedResponse
    {
        return $this->byToken($token)->item->streamAttachmentResponse();
    }

    /** Email on or off for each kind, and muting the item's lodge, from the email's page. */
    public function publicChoices(Request $request, string $token): HttpResponse
    {
        $copy = $this->byToken($token);
        $validated = $request->validate([
            'email' => ['sometimes', 'array'],
            'email.*' => [Rule::in(PassedOnPreferences::KINDS)],
            'mute' => ['sometimes', 'boolean'],
        ]);

        $owner = $this->owner($copy);
        $prefs = $this->prefsFor($owner);
        foreach (PassedOnPreferences::KINDS as $kind) {
            $prefs = $prefs->withChannels($kind, $this->switchEmail($prefs->channels($kind), in_array($kind, $validated['email'] ?? [], true)));
        }
        if ($copy->item->lodge_id) {
            $prefs = $prefs->withMuted($copy->item->lodge_id, $request->boolean('mute'));
        }
        $this->save($owner, $prefs);

        return response()->view('passed-on.public', array_merge($this->publicData($copy), ['saved' => true]));
    }

    /** One-click unsubscribe (also what mail programs post to): no more email of this kind. */
    public function unsubscribe(string $token): HttpResponse
    {
        $copy = $this->byToken($token);
        $owner = $this->owner($copy);
        $prefs = $this->prefsFor($owner);
        $this->save($owner, $prefs->withChannels($copy->kind, $this->switchEmail($prefs->channels($copy->kind), false)));

        return response()->view('passed-on.public', array_merge($this->publicData($copy), ['unsubscribed' => true]));
    }

    private function mine(Request $request, int $delivery): PassedOnDelivery
    {
        return PassedOnDelivery::where('user_id', $request->user()->id)->with(['item.lodge.clubType:id,code,name'])->findOrFail($delivery);
    }

    private function byToken(string $token): PassedOnDelivery
    {
        abort_unless(strlen($token) === 64, 404);

        return PassedOnDelivery::where('token', $token)->with(['item.lodge.clubType:id,code,name', 'user', 'member'])->firstOrFail();
    }

    private function owner(PassedOnDelivery $copy): User|Member
    {
        $owner = $copy->user ?? $copy->member;
        abort_unless($owner !== null, 404);

        return $owner;
    }

    private function prefsFor(User|Member $owner): PassedOnPreferences
    {
        return PassedOnPreferences::fromArray($owner->passed_on_prefs, hasAccount: $owner instanceof User);
    }

    private function save(User|Member $owner, PassedOnPreferences $prefs): void
    {
        // Quietly: a change of what someone hears about is not a change to the member's record.
        $owner->forceFill(['passed_on_prefs' => $prefs->toArray()])->saveQuietly();
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    private function switchEmail(array $channels, bool $on): array
    {
        return array_values(array_unique($on ? [...$channels, 'email'] : array_diff($channels, ['email'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function publicData(PassedOnDelivery $copy): array
    {
        $prefs = $this->prefsFor($this->owner($copy));

        return [
            'copy' => $copy,
            'item' => $copy->item,
            'kindLabel' => PassedOnPreferences::LABELS[$copy->kind] ?? '',
            'labels' => PassedOnPreferences::LABELS,
            'emailOn' => collect(PassedOnPreferences::KINDS)->filter(fn (string $kind) => in_array('email', $prefs->channels($kind), true))->values()->all(),
            'muted' => $prefs->mutes($copy->item->lodge_id),
            'saved' => false,
            'unsubscribed' => false,
        ];
    }
}
