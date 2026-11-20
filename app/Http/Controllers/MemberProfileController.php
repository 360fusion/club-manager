<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\Member;
use App\Mail\LodgeClaimMail;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ClubAccess;
use App\Support\EmailChange;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's own profile and dues pages in the member area. Split out of MemberPortalController; the URLs, route names and permissions are unchanged.
 */
class MemberProfileController extends Controller
{
    /** The postal address on the lodge's roster, which a member keeps up to date themselves. */
    private const ADDRESS_FIELDS = ['address_line_1', 'address_line_2', 'city', 'county', 'postcode'];

    /**
     * Display member dues & invoices page inside member portal.
     */
    public function dues(Request $request, string $slug): Response
    {
        $user = Auth::user();
        $club = Club::where('slug', $slug)
            ->with('membershipPlans')
            ->firstOrFail();

        abort_unless(ClubAccess::isActiveMember(Auth::user(), $club), 403, 'You are not a member of this club.');

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        $invoices = $user ? Invoice::where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'draft')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'title' => $inv->title,
                'amount' => number_format((float) $inv->amount, 2),
                'status' => $inv->status,
                'paid_at' => $inv->paid_at?->format('j M Y'),
                'created_at' => $inv->created_at?->format('j M Y'),
            ]) : [];

        $plans = $club->membershipPlans->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => number_format((float) $p->price, 2),
            'billing_period' => $p->billing_period,
        ]);

        return Inertia::render('Member/Dues', [
            'club' => $club->only(['id', 'name', 'slug', 'lodge_number']),
            'memberRole' => $memberPivot->role ?? 'member',
            'memberNumber' => $memberPivot?->member_number,
            'plans' => $plans,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display member profile & account security page inside member portal.
     */
    public function profile(Request $request, string $slug): Response
    {
        $user = Auth::user();
        $club = $this->findClub($slug);

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;
        abort_if($memberPivot === null && ! $user?->actsAsSuperAdmin(), 403, 'You are not a member of this club.');

        $accMember = Member::where('club_id', $club->id)->where('user_id', $user->id)->first();

        return Inertia::render('Member/Profile', [
            'club' => $club->only(['id', 'name', 'slug', 'lodge_number']),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : asset('storage/'.$user->avatar_url)) : null,
                'pending_email' => $user->pending_email,
                'two_factor_enabled' => ! empty($user->two_factor_secret),
            ],
            'memberRole' => $memberPivot->role ?? 'member',
            'memberNumber' => $memberPivot?->member_number,
            'memberProfile' => [
                'role' => $memberPivot->role ?? 'member',
                'member_number' => $memberPivot?->member_number,
                'phone' => $memberPivot->phone ?? '',
                'emergency_contact' => $memberPivot->emergency_contact ?? '',
                'dietary_notes' => $memberPivot->dietary_notes ?? '',
                'address' => $accMember?->only(self::ADDRESS_FIELDS),
                'masonic_rank' => $accMember->masonic_rank ?? 'Bro',
                'grand_rank' => $accMember->grand_rank ?? null,
                'provincial_rank' => $accMember->provincial_rank ?? null,
                'grand_lodge_number' => $accMember->grand_lodge_number ?? null,
                'date_of_initiation' => $accMember?->date_of_initiation?->format('d M Y') ?? null,
                'date_of_passing' => $accMember?->date_of_passing?->format('d M Y') ?? null,
                'date_of_raising' => $accMember?->date_of_raising?->format('d M Y') ?? null,
                'date_of_joining' => $accMember?->date_of_joining?->format('d M Y') ?? null,
            ],
        ]);
    }

    /**
     * Update member profile information, avatar photo, and club-specific preferences.
     */
    public function updateProfile(Request $request, string $slug): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $club = $this->findClub($slug);

        abort_unless($user->clubs()->where('clubs.id', $club->id)->exists(), 403, 'You are not a member of this club.');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            // Changing the sign-in address hands over the account, so it needs the current password.
            'current_password' => [Rule::requiredIf(fn () => strcasecmp((string) $request->input('email'), (string) $user->email) !== 0), 'nullable', 'string', 'current_password'],
            'avatar_url' => ['nullable', 'string', 'max:1000', 'regex:#^(https?://|avatars/)#i'],
            'avatar' => UploadRules::image(4096),
            'phone' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'dietary_notes' => ['nullable', 'string', 'max:1000'],
            ...array_fill_keys(self::ADDRESS_FIELDS, ['nullable', 'string', 'max:255']),
        ]);

        $roster = Member::where('club_id', $club->id)->where('user_id', $user->id)->first();
        $before = $this->contactDetails($user, $club, $roster);

        $user->fill($request->only('name'));
        $emailHeld = strcasecmp((string) $request->input('email'), (string) $user->email) !== 0 && EmailChange::request($user, (string) $request->input('email'));

        if ($request->hasFile('avatar')) {
            ImageDownscaler::apply($request->file('avatar'), 800);
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        } elseif ($request->filled('avatar_url')) {
            $user->avatar_url = $request->avatar_url;
        }

        $user->save();

        if ($emailHeld) {
            EmailChange::sendLink($user);
        }

        // Update club-specific member pivot attributes
        $user->clubs()->updateExistingPivot($club->id, [
            'phone' => $request->input('phone'),
            'emergency_contact' => $request->input('emergency_contact'),
            'dietary_notes' => $request->input('dietary_notes'),
        ]);

        // The roster is the Secretary's record, so the phone and address are kept there too.
        $roster?->update(['phone' => $request->input('phone')] + $request->only(self::ADDRESS_FIELDS));
        $this->tellSecretary($club, $user, $before, $this->contactDetails($user->fresh(), $club, $roster?->fresh()));

        return redirect()->back()->with('success', $emailHeld ? "We sent a link to {$user->pending_email}. Your sign-in email stays {$user->email} until you open it." : 'Profile and club membership preferences updated successfully.');
    }

    /**
     * The details the Secretary keeps for the lodge's records, by the label used in the email.
     *
     * @return array<string, string>
     */
    private function contactDetails(User $user, Club $club, ?Member $roster): array
    {
        $pivot = $user->clubs()->where('clubs.id', $club->id)->first()?->pivot;
        $address = $roster ? implode(', ', array_filter($roster->only(self::ADDRESS_FIELDS))) : '';

        return array_filter([
            'Name' => $user->name,
            'Phone' => (string) ($pivot->phone ?? ''),
            'Emergency contact' => (string) ($pivot->emergency_contact ?? ''),
            'Address' => $roster ? $address : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Email the Secretary what a member changed, so the lodge's records and returns stay right. A Secretary changing
     * their own details is not told.
     *
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     */
    private function tellSecretary(Club $club, User $user, array $before, array $after): void
    {
        $changes = collect($after)->filter(fn ($value, $label) => ($before[$label] ?? '') !== $value)
            ->map(fn ($value, $label) => "{$label}: ".(($before[$label] ?? '') ?: '(none)').' → '.($value ?: '(none)'));
        $secretary = (Member::summonsSecretary($club)['email'] ?? null) ?: $club->email;

        if ($changes->isEmpty() || ! $secretary || strcasecmp($secretary, (string) $user->email) === 0) {
            return;
        }

        Mail::to($secretary)->queue(new LodgeClaimMail(
            "{$user->name} updated their details",
            "{$user->name} changed their details for {$club->name}:\n\n".$changes->implode("\n"),
            'Open their record',
            route('admin.users.show', ['clubSlug' => $club->slug, 'userId' => $user->id]),
        ));
    }
}
