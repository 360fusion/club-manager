<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Http\Requests\StorePassedOnItemRequest;
use App\Jobs\SendPassedOnItem;
use App\Models\Club;
use App\Models\ClubLodgeLink;
use App\Models\Lodge;
use App\Models\PassedOnItem;
use App\Models\User;
use App\Services\PassedOn\LodgeLinks;
use App\Services\PassedOn\PassedOnAudience;
use App\Services\PassedOn\PlatformSummons;
use App\Support\ClubAccess;
use App\Support\PassedOnPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Secretary's side of passing things on: the lodge's sister and reciprocal lists, and passing on a summons or
 * notice from another lodge, or a Provincial or Grand Lodge communication. In the member area, made for a phone.
 */
class PassOnController extends Controller
{
    /** The capability (owner, admin and secretary roles by default) or the Secretary office. */
    public static function canSend(?User $user, Club $club): bool
    {
        return ClubAccess::can($user, $club, 'pass_on_communications') || Member::userHoldsOffice($club, $user, [LodgeOffice::Secretary]);
    }

    public function index(Request $request, string $slug, LodgeLinks $links): Response
    {
        $club = $this->authorizedClub($request, $slug);
        $lodges = fn (string $relation) => $links->lodges($club, $relation)
            ->map(fn (Lodge $lodge) => $this->lodgeRow($lodge) + ['publishes_own' => PlatformSummons::publishesOwn($lodge)])->values();

        return Inertia::render('Member/PassOn/Index', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'kinds' => collect(PassedOnPreferences::LABELS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'lodges' => ['sister' => $lodges(ClubLodgeLink::SISTER), 'reciprocal' => $lodges(ClubLodgeLink::RECIPROCAL)],
            'sent' => PassedOnItem::where('club_id', $club->id)->where('source', PassedOnItem::MANUAL)->with('lodge.clubType:id,code,name')->latest('id')->limit(20)->get()
                ->map(fn (PassedOnItem $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'kind' => PassedOnPreferences::LABELS[$item->kind] ?? '',
                    'lodge' => $item->lodge?->displayName(),
                    'meeting_date' => $item->meeting_date?->format('j M Y'),
                    'sent_on' => $item->created_at->format('j M Y'),
                    'sending' => $item->sent_at === null,
                    'recipients' => $item->recipient_count,
                    'emails' => $item->email_count,
                    'attachment_url' => $item->attachment_path ? route('member.pass_on.attachment', ['slug' => $club->slug, 'item' => $item->id]) : null,
                ]),
        ]);
    }

    public function store(StorePassedOnItemRequest $request, string $slug, PassedOnAudience $audience): RedirectResponse
    {
        $club = $this->findClub($slug);
        $data = $request->validated();
        $lodge = $request->lodge();
        $file = $request->file('attachment');
        $date = $data['type'] === PassedOnItem::SUMMONS && ! empty($data['meeting_date']) ? Carbon::parse($data['meeting_date'])->toDateString() : null;

        $item = PassedOnItem::create([
            'club_id' => $club->id,
            'sent_by' => $request->user()->id,
            'passed_on_by' => PassedOnItem::byLine($club, $request->user()),
            'source' => PassedOnItem::MANUAL,
            'kind' => $data['kind'],
            'type' => $data['type'],
            'lodge_id' => $lodge?->id,
            'title' => $data['title'],
            'body' => self::asHtml($data['body'] ?? null),
            'meeting_date' => $date,
            'meeting_time' => $date ? ($data['meeting_time'] ?? null) : null,
            'venue' => $date ? ($data['venue'] ?? null) : null,
            'dress_code' => $date ? ($data['dress_code'] ?? null) : null,
            'dedupe_key' => PassedOnItem::dedupeKeyFor($data['type'], $data['kind'], $lodge?->id, $date, $file ? (string) file_get_contents($file->getRealPath()) : null, $data['title'], $data['body'] ?? null),
        ]);

        if ($file) {
            $item->update([
                'attachment_path' => $file->store('passed-on/'.$club->id, 'local'),
                'attachment_name' => Str::limit($file->getClientOriginalName(), 150, ''),
            ]);
        }

        // Without followers: a follow is private, and so is how many a lodge has.
        $count = $audience->recipients($item->load(['club', 'lodge']))->where('follower', false)->count();
        SendPassedOnItem::dispatch($item->id);

        return redirect()->route('member.pass_on', ['slug' => $club->slug])
            ->with('success', 'Passed on. It is going to '.$count.' '.Str::plural('person', $count).'.');
    }

    /** The PDF of something this lodge passed on, for its senders. */
    public function attachment(Request $request, string $slug, int $item): StreamedResponse
    {
        $club = $this->authorizedClub($request, $slug);
        $passedOn = PassedOnItem::where('club_id', $club->id)->whereNotNull('attachment_path')->findOrFail($item);

        return $passedOn->streamAttachmentResponse();
    }

    public function lists(Request $request, string $slug, LodgeLinks $links): Response
    {
        $club = $this->authorizedClub($request, $slug);
        $search = trim((string) $request->query('q', ''));

        return Inertia::render('Member/PassOn/Lists', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'sister' => $links->lodges($club, ClubLodgeLink::SISTER)->map(fn (Lodge $lodge) => $this->lodgeRow($lodge))->values(),
            'reciprocal' => $links->lodges($club, ClubLodgeLink::RECIPROCAL)->map(fn (Lodge $lodge) => $this->lodgeRow($lodge))->values(),
            'hall' => [
                'name' => $club->lodge?->masonicHall?->name,
                'lodges' => $links->hallLodges($club)->map(fn (Lodge $lodge) => $this->lodgeRow($lodge) + ['excluded' => (bool) $lodge->getAttribute('excluded')])->values(),
            ],
            'search' => $search,
            'results' => mb_strlen($search) < 2 ? [] : Lodge::listed()
                ->where(fn ($match) => $match->whereLike('name', '%'.$search.'%')->orWhere('number', $search))
                ->when($club->lodge, fn ($query) => $query->where('id', '!=', $club->lodge->id))
                ->with(['clubType:id,code,name', 'masonicHall:id,name,town'])->orderBy('name')->limit(15)->get()
                // Many lodges share a name: the number and town tell them apart.
                ->map(fn (Lodge $lodge) => $this->lodgeRow($lodge) + ['detail' => implode(' · ', array_filter([$lodge->number ? 'No. '.$lodge->number : null, $lodge->masonicHall?->town]))])->values(),
        ]);
    }

    public function updateLists(Request $request, string $slug, LodgeLinks $links): RedirectResponse
    {
        $club = $this->authorizedClub($request, $slug);
        $validated = $request->validate([
            'lodge_slug' => ['required', 'string', 'max:200'],
            'relation' => ['required', Rule::in(ClubLodgeLink::RELATIONS)],
            'action' => ['required', Rule::in(['add', 'remove'])],
        ]);

        $lodge = Lodge::listed()->where('slug', $validated['lodge_slug'])->firstOrFail();
        abort_if($lodge->id === $club->lodge?->id, 422, 'That is your own lodge.');

        $validated['action'] === 'add' ? $links->add($club, $lodge, $validated['relation']) : $links->remove($club, $lodge, $validated['relation']);

        $list = $validated['relation'] === ClubLodgeLink::SISTER ? 'sister lodges' : 'reciprocal visit lodges';

        return back()->with('success', $lodge->displayName().($validated['action'] === 'add' ? " is on your {$list}." : " is off your {$list}."));
    }

    /** Text pasted from an email keeps its paragraphs and line breaks; anything already HTML is left to the sanitiser. */
    private static function asHtml(?string $text): ?string
    {
        if ($text === null || trim($text) === '' || $text !== strip_tags($text)) {
            return $text;
        }

        return collect(preg_split('/\R\s*\R/u', trim($text)) ?: [])
            ->map(fn (string $paragraph) => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }

    private function authorizedClub(Request $request, string $slug): Club
    {
        $club = $this->findClub($slug);
        abort_unless(self::canSend($request->user(), $club), 403, 'Only the Secretary and the lodge\'s admins can pass things on.');

        return $club;
    }

    /**
     * @return array{id: int, slug: string, name: string}
     */
    private function lodgeRow(Lodge $lodge): array
    {
        return ['id' => $lodge->id, 'slug' => $lodge->slug, 'name' => $lodge->displayName()];
    }
}
