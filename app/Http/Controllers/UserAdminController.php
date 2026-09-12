<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserAdminController extends Controller
{
    /**
     * Display member roster for a club in the admin portal.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['users'])
            ->firstOrFail();

        $members = $club->users->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role ?? 'member',
                'member_number' => $u->pivot->member_number ?? ('MEM-'.$u->id),
                'status' => $u->pivot->status ?? 'active',
                'joined_at' => $u->pivot->created_at?->format('M d, Y') ?? 'Recent',
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'club' => $club,
            'members' => $members,
        ]);
    }

    /**
     * Store a newly created member in the club roster.
     */
    public function storeMember(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => 'required|in:owner,admin,coach,member,treasurer',
            'member_number' => 'nullable|string|max:100',
        ]);

        $user = User::firstOrCreate(
            ['email' => strtolower($validated['email'])],
            [
                'name' => $validated['name'],
                'password' => Hash::make('password123'),
            ]
        );

        if ($club->users()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'User is already a member of this club.');
        }

        $club->users()->attach($user->id, [
            'role' => $validated['role'],
            'member_number' => $validated['member_number'] ?: ('MEM-'.rand(1000, 9999)),
            'status' => 'active',
        ]);

        return redirect()->back()->with('success', 'Member added successfully to roster.');
    }

    /**
     * Update a member's role in the club.
     */
    public function updateRole(Request $request, string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'role' => 'required|in:owner,admin,coach,member,treasurer',
        ]);

        $club->users()->updateExistingPivot($userId, ['role' => $validated['role']]);

        return redirect()->back()->with('success', 'Member role updated to '.strtoupper($validated['role']));
    }

    /**
     * Remove a member from the club.
     */
    public function removeMember(string $clubSlug, int $userId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->users()->detach($userId);

        return redirect()->back()->with('success', 'Member removed from roster.');
    }
}
