<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuperAdminLodgeCorrection\ApplyRequest;
use App\Http\Requests\SuperAdminLodgeCorrection\Reject2Request;
use App\Models\Lodge;
use App\Models\LodgeCorrection;
use App\Models\MasonicHall;
use App\Services\Lodges\LodgeCorrectionService;
use App\Support\MeetingScheduleParser;
use App\Support\Months;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The superadmin queue of suggested fixes to directory listings: compare each one with the listing,
 * adjust it if needed, then apply it or turn it down.
 */
class SuperAdminLodgeCorrectionController extends Controller
{
    public function __construct(private readonly LodgeCorrectionService $corrections) {}

    /**
     * The suggestions this screen may list and decide: every one, for a superadmin.
     *
     * @return Builder<LodgeCorrection>
     */
    protected function scope(Request $request): Builder
    {
        return LodgeCorrection::query();
    }

    /**
     * The route names (index, apply, reject) and layout the page uses.
     *
     * @return array{routes: string, layout: string}
     */
    protected function screen(): array
    {
        return ['routes' => 'superadmin.lodge_corrections', 'layout' => 'superadmin'];
    }

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'open');
        $status = in_array($status, ['open', 'applied', 'rejected', 'all'], true) ? $status : 'open';

        $query = $this->scope($request)
            ->with(['lodge.clubType:id,code,name', 'lodge.province:id,name', 'lodge.masonicHall:id,name,town', 'lodge.club:id,name,slug', 'user', 'decidedBy:id,name'])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when(in_array($status, ['applied', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest('id');

        $halls = MasonicHall::orderBy('name')->get(['id', 'name', 'town']);
        $hallNames = $halls->mapWithKeys(fn (MasonicHall $hall) => [$hall->id => $hall->name.($hall->town ? ', '.$hall->town : '')]);

        return Inertia::render('SuperAdmin/LodgeCorrections/Index', [
            ...$this->screen(),
            'corrections' => $query->paginate(25)->withQueryString()->through(fn (LodgeCorrection $correction) => $this->present($correction, $hallNames->all())),
            'status' => $status,
            'counts' => [
                'open' => $this->scope($request)->open()->count(),
                'applied' => $this->scope($request)->where('status', LodgeCorrection::APPLIED)->count(),
            ],
            'fields' => LodgeCorrection::FIELDS,
            'halls' => $halls,
            'statuses' => Lodge::STATUSES,
        ]);
    }

    public function apply(ApplyRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        // A ticked field the form sent no value for is being emptied.
        $values = collect($validated['fields'])->mapWithKeys(fn (string $field) => [$field => $validated['values'][$field] ?? null])->all();

        $this->corrections->apply($this->scope($request)->findOrFail($id), $request->user(), $values, $validated['note'] ?? null);

        return back()->with('success', 'Applied. The listing is updated and the person has been thanked.');
    }

    public function reject(Reject2Request $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $this->corrections->reject($this->scope($request)->findOrFail($id), $request->user(), $validated['reason']);

        return back()->with('success', 'Turned down, and the person has been told why.');
    }

    /**
     * @param  array<int, string>  $hallNames
     * @return array<string, mixed>
     */
    private function present(LodgeCorrection $correction, array $hallNames): array
    {
        $lodge = $correction->lodge;
        $user = $correction->user;
        $show = fn (string $field, mixed $value) => match (true) {
            $value === null || $value === '' => null,
            $field === 'masonic_hall_id' => $hallNames[$value] ?? 'Hall #'.$value,
            $field === 'installation_month' => Months::name((int) $value),
            default => (string) $value,
        };
        $wording = $correction->changes['meets_text'] ?? null;
        $parser = new MeetingScheduleParser;

        return [
            'id' => $correction->id,
            'status' => $correction->status,
            'open' => $correction->isOpen(),
            'note' => $correction->note,
            'decision_note' => $correction->decision_note,
            'decided_by' => $correction->decidedBy?->name,
            'decided_at' => $correction->decided_at?->toDateTimeString(),
            'created_at' => $correction->created_at->toDateTimeString(),
            'lodge' => [
                'name' => $lodge->displayName(),
                'number' => $lodge->number,
                'slug' => $lodge->slug,
                'listed' => $lodge->status === 'active',
                'order' => $lodge->clubType?->name,
                'province' => $lodge->province?->name,
                'club' => $lodge->club ? ['name' => $lodge->club->name, 'slug' => $lodge->club->slug] : null,
            ],
            'suggester' => [
                'name' => $user->name,
                'email' => $user->email,
                'joined' => $user->created_at->toDateString(),
                'email_verified' => $user->email_verified_at !== null,
            ],
            // Every detail as the listing has it now, so any of them can be changed when applying.
            'listing' => $lodge->only(['meets_text', 'masonic_hall_id', 'status', 'name', 'number', 'website_url']),
            'changes' => collect($correction->changes)->map(fn (mixed $value, string $field) => [
                'field' => $field,
                'current' => $field === 'hall_text' ? $show('masonic_hall_id', $lodge->masonic_hall_id) : $show($field, $lodge->{$field}),
                'value' => $value,
                'shown' => $show($field, $value),
            ])->values(),
            // What the parser reads from the suggested wording, so the result can be checked before it is applied.
            'parsed' => $wording ? [
                'patterns' => $parser->parseAll($wording),
                'installation_month' => Months::name($parser->installationMonth($wording)),
            ] : null,
            'applied' => collect($correction->applied ?? [])->except('schedules')->map(fn (array $change, string $field) => [
                'field' => $field,
                'from' => $show($field, $change['from']),
                'to' => $show($field, $change['to']),
            ])->values(),
            'others_waiting' => $correction->isOpen() ? $lodge->corrections()->open()->where('id', '!=', $correction->id)->count() : 0,
        ];
    }
}
