<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Lodge;
use App\Models\LodgeClaim;
use App\Services\Lodges\LodgeClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The superadmin queue for lodge claims: check the person really belongs to the lodge, then
 * approve, refuse or ask for more.
 */
class SuperAdminLodgeClaimController extends Controller
{
    public function __construct(private readonly LodgeClaimService $claims) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'open');
        $status = in_array($status, ['open', 'approved', 'rejected', 'all'], true) ? $status : 'open';

        $query = LodgeClaim::query()
            ->with(['lodge.clubType:id,name', 'lodge.province:id,name', 'lodge.masonicHall:id,name,town,postcode', 'user', 'club:id,name,slug', 'decidedBy:id,name', 'events.actor:id,name'])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'approved', fn ($q) => $q->where('status', LodgeClaim::APPROVED))
            ->when($status === 'rejected', fn ($q) => $q->whereIn('status', [LodgeClaim::REJECTED, LodgeClaim::WITHDRAWN, LodgeClaim::SUPERSEDED]))
            ->orderByRaw("case when status in ('pending', 'more_info') then 0 else 1 end")
            ->latest('id');

        return Inertia::render('SuperAdmin/LodgeClaims/Index', [
            'claims' => $query->paginate(25)->withQueryString()->through(fn (LodgeClaim $claim) => $this->present($claim)),
            'status' => $status,
            'counts' => [
                'open' => LodgeClaim::open()->count(),
                'approved' => LodgeClaim::where('status', LodgeClaim::APPROVED)->count(),
            ],
            'clubs' => Club::whereDoesntHave('lodge')->orderBy('name')->limit(500)->get(['id', 'name', 'slug']),
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $claim = LodgeClaim::findOrFail($id);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['new', 'link'])],
            'club_id' => ['required_if:mode,link', 'nullable', 'exists:clubs,id'],
            'make_claimant_admin' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validated['mode'] === 'link') {
            $this->claims->approveByLinking($claim, $request->user(), Club::findOrFail($validated['club_id']), (bool) ($validated['make_claimant_admin'] ?? false), $validated['note'] ?? null);

            return back()->with('success', 'Approved, and the listing is linked to the existing club.');
        }

        $club = $this->claims->approveWithNewClub($claim, $request->user(), $validated['note'] ?? null);

        return back()->with('success', "Approved. {$club->name} has been created and {$claim->user->name} is its owner.");
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|min:3|max:1000']);

        $this->claims->reject(LodgeClaim::findOrFail($id), $request->user(), $validated['reason']);

        return back()->with('success', 'The claim was refused and the person has been told why.');
    }

    public function requestInfo(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate(['question' => 'required|string|min:3|max:1000']);

        $this->claims->requestInfo(LodgeClaim::findOrFail($id), $request->user(), $validated['question']);

        return back()->with('success', 'Your question has been sent.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LodgeClaim $claim): array
    {
        $user = $claim->user;
        $lodge = $claim->lodge;

        return [
            'id' => $claim->id,
            'status' => $claim->status,
            'open' => $claim->isOpen(),
            'claimant_role' => LodgeClaim::ROLES[$claim->claimant_role] ?? $claim->claimant_role,
            'message' => $claim->message,
            'phone' => $claim->phone,
            'evidence' => $claim->evidence,
            'decision_note' => $claim->decision_note,
            'decided_by' => $claim->decidedBy?->name,
            'decided_at' => $claim->decided_at?->toDateTimeString(),
            'created_at' => $claim->created_at->toDateTimeString(),
            'club' => $claim->club ? ['name' => $claim->club->name, 'slug' => $claim->club->slug] : null,
            'lodge' => [
                'id' => $lodge->id,
                'name' => $lodge->displayName(),
                'number' => $lodge->number,
                'slug' => $lodge->slug,
                'order' => $lodge->clubType?->name,
                'province' => $lodge->province?->name,
                'hall' => $lodge->masonicHall ? trim($lodge->masonicHall->name.', '.$lodge->masonicHall->town.' '.$lodge->masonicHall->postcode) : null,
                'meets_text' => $lodge->meets_text,
                'source_url' => $lodge->source_url,
                'is_managed' => $lodge->isManaged(),
            ],
            'claimant' => [
                'name' => $user->name,
                'email' => $user->email,
                'joined' => $user->created_at->toDateString(),
                'email_verified' => $user->email_verified_at !== null,
                'clubs' => $user->clubs()->pluck('clubs.name')->all(),
                'other_claims' => LodgeClaim::where('user_id', $user->id)->where('id', '!=', $claim->id)->count(),
            ],
            'others_waiting' => $lodge->claims()->open()->where('id', '!=', $claim->id)->count(),
            'events' => $claim->events->map(fn ($event) => [
                'type' => $event->type,
                'note' => $event->note,
                'actor' => $event->actor?->name,
                'at' => $event->created_at->toDateTimeString(),
            ])->all(),
        ];
    }
}
