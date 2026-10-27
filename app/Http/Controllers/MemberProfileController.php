<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Invoice;
use App\Support\ClubAccess;
use App\Support\EmailVerification;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's own profile and dues pages in the member area. Split out of MemberPortalController; the URLs, route names and permissions are unchanged.
 */
class MemberProfileController extends Controller
{
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
            'club' => $club,
            'memberRole' => $memberPivot->role ?? 'member',
            'memberNumber' => $memberPivot->member_number ?? 'MEM-1001',
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
        abort_if($memberPivot === null && ! $user?->is_super_admin, 403, 'You are not a member of this club.');

        $accMember = $user ? Member::where('club_id', $club->id)->where('user_id', $user->id)->first() : null;

        return Inertia::render('Member/Profile', [
            'club' => $club,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : asset('storage/'.$user->avatar_url)) : null,
                'two_factor_enabled' => ! empty($user->two_factor_secret),
            ] : null,
            'memberRole' => $memberPivot->role ?? 'member',
            'memberNumber' => $memberPivot->member_number ?? 'MEM-1001',
            'memberProfile' => [
                'role' => $memberPivot->role ?? 'member',
                'member_number' => $memberPivot->member_number ?? 'MEM-1001',
                'phone' => $memberPivot->phone ?? '',
                'emergency_contact' => $memberPivot->emergency_contact ?? '',
                'dietary_notes' => $memberPivot->dietary_notes ?? '',
                'masonic_rank' => $accMember?->masonic_rank ?? 'Bro',
                'grand_rank' => $accMember?->grand_rank ?? null,
                'provincial_rank' => $accMember?->provincial_rank ?? null,
                'grand_lodge_number' => $accMember?->grand_lodge_number ?? null,
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
            'avatar_url' => ['nullable', 'string', 'max:1000', 'regex:#^(https?://|avatars/)#i'],
            'avatar' => UploadRules::image(4096),
            'phone' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'dietary_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->fill($request->only('name', 'email'));

        if ($request->hasFile('avatar')) {
            ImageDownscaler::apply($request->file('avatar'), 800);
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        } elseif ($request->filled('avatar_url')) {
            $user->avatar_url = $request->avatar_url;
        }

        $emailChanged = $user->isDirty('email');

        if ($emailChanged && EmailVerification::required()) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && EmailVerification::required()) {
            $user->sendEmailVerificationNotification();
        }

        // Update club-specific member pivot attributes
        $user->clubs()->updateExistingPivot($club->id, [
            'phone' => $request->input('phone'),
            'emergency_contact' => $request->input('emergency_contact'),
            'dietary_notes' => $request->input('dietary_notes'),
        ]);

        return redirect()->back()->with('success', 'Profile and club membership preferences updated successfully.');
    }
}
