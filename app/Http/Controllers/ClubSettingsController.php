<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubSettingsController extends Controller
{
    /**
     * Default Granular Permissions Matrix for Club Roles.
     */
    public static function defaultPermissionMatrix(): array
    {
        return [
            'manage_roster' => [
                'label' => 'Manage Roster & Approve Members',
                'description' => 'Add, approve, reject, or remove members from roster',
                'roles' => ['owner', 'admin'],
            ],
            'manage_events' => [
                'label' => 'Create & Edit Events / Check-Ins',
                'description' => 'Publish classes, events, dining summons, and take attendance',
                'roles' => ['owner', 'admin', 'coach'],
            ],
            'manage_subscriptions' => [
                'label' => 'Manage Subscriptions & Invoicing',
                'description' => 'Configure dues tiers, generate receipts, and issue refunds',
                'roles' => ['owner', 'admin', 'treasurer'],
            ],
            'send_broadcasts' => [
                'label' => 'Send Newsletters & Broadcast Messages',
                'description' => 'Create and dispatch email broadcasts and news posts',
                'roles' => ['owner', 'admin', 'coach'],
            ],
            'edit_website' => [
                'label' => 'Edit Website & CMS Pages',
                'description' => 'Build CMS pages, customize navigation, and update site content',
                'roles' => ['owner', 'admin'],
            ],
            'view_analytics' => [
                'label' => 'Access Financial Reports & Exports',
                'description' => 'View revenue analytics, attendance rates, and export CSV data',
                'roles' => ['owner', 'admin', 'treasurer'],
            ],
        ];
    }

    /**
     * Display club settings dashboard with top-level tabs.
     */
    public function show(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['clubType', 'users'])
            ->firstOrFail();

        $allModules = [
            ['code' => 'website_builder', 'name' => 'Website Builder & CMS Pages', 'description' => 'Custom pages, hero banners, and site navigation', 'icon' => '🌐'],
            ['code' => 'memberships', 'name' => 'Subscriptions & Dues', 'description' => 'Recurring membership tiers, pricing, and receipts', 'icon' => '💳'],
            ['code' => 'events', 'name' => 'Classes & Events', 'description' => 'Event schedules, RSVPs, and attendance check-in', 'icon' => '📅'],
            ['code' => 'dining_and_summons', 'name' => 'Dining & Menu Selections', 'description' => 'Dinner RSVPs, meal choices, and dietary notes', 'icon' => '🍽️'],
            ['code' => 'boat_reservations', 'name' => 'Equipment & Pitch Bookings', 'description' => 'Boat bay, pitch, or facility reservations', 'icon' => '🚣'],
            ['code' => 'erg_scores', 'name' => 'Athletic & Performance Log', 'description' => 'Erg scores, fitness tests, and leaderboards', 'icon' => '📊'],
            ['code' => 'newsletters', 'name' => 'Newsletters & Communications', 'description' => 'Bulk email broadcasts and announcements', 'icon' => '✉️'],
            ['code' => 'donations', 'name' => 'Donations & Fundraising', 'description' => 'Fundraising campaigns and donor receipts', 'icon' => '🎁'],
        ];

        $settings = array_merge([
            'tagline' => 'Excellence in Club Management',
            'primary_color' => '#0369a1',
            'sidebar_theme' => 'dark_slate',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'contact_email' => 'admin@'.$club->slug.'.org',
            'phone' => '+44 20 7946 0912',
            'address' => '100 Boathouse Way, Oxford, UK',
            'social_facebook' => 'https://facebook.com',
            'social_instagram' => 'https://instagram.com',
            'social_twitter' => 'https://x.com',
            'registration_mode' => 'open',
            'member_prefix' => strtoupper(substr($club->slug, 0, 4)).'-',
            'default_role' => 'member',
            'enabled_modules' => $club->clubType->available_modules ?? [],
            'permission_matrix' => self::defaultPermissionMatrix(),
            'notify_event_reminders' => true,
            'notify_dues_overdue' => true,
            'email_from_name' => $club->name,
        ], $club->settings ?? []);

        $members = $club->users->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role ?? 'member',
            'member_number' => $u->pivot->member_number ?? '',
            'status' => $u->pivot->status ?? 'active',
        ]);

        return Inertia::render('Admin/Settings/Show', [
            'club' => $club,
            'settings' => $settings,
            'allModules' => $allModules,
            'members' => $members,
            'availableRoles' => [
                ['code' => 'owner', 'name' => 'Owner', 'badge' => 'bg-purple-100 text-purple-800 border-purple-200'],
                ['code' => 'admin', 'name' => 'Admin', 'badge' => 'bg-indigo-100 text-indigo-800 border-indigo-200'],
                ['code' => 'coach', 'name' => 'Coach', 'badge' => 'bg-sky-100 text-sky-800 border-sky-200'],
                ['code' => 'treasurer', 'name' => 'Treasurer', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200'],
                ['code' => 'member', 'name' => 'Member', 'badge' => 'bg-slate-100 text-slate-800 border-slate-200'],
            ],
        ]);
    }

    /**
     * Update club settings and permissions matrix.
     */
    public function update(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:50',
            'sidebar_theme' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'social_facebook' => 'nullable|string|max:500',
            'social_instagram' => 'nullable|string|max:500',
            'social_twitter' => 'nullable|string|max:500',
            'registration_mode' => 'nullable|in:open,invite_only',
            'member_prefix' => 'nullable|string|max:50',
            'default_role' => 'nullable|in:member,coach,treasurer,admin',
            'custom_domain' => 'nullable|string|max:255',
            'enabled_modules' => 'nullable|array',
            'permission_matrix' => 'nullable|array',
            'notify_event_reminders' => 'nullable|boolean',
            'notify_dues_overdue' => 'nullable|boolean',
            'email_from_name' => 'nullable|string|max:255',
        ]);

        if (isset($validated['name'])) {
            $club->name = $validated['name'];
        }

        if (isset($validated['logo_url'])) {
            $club->logo_url = $validated['logo_url'];
        }

        if (isset($validated['custom_domain']) && $validated['custom_domain'] !== $club->custom_domain) {
            $club->custom_domain = strtolower(trim($validated['custom_domain']));
            $club->domain_status = 'pending';
            $club->domain_verified_at = null;
        }

        $existingSettings = $club->settings ?? [];
        $mergedSettings = array_merge($existingSettings, $validated);

        $club->settings = $mergedSettings;
        $club->save();

        return redirect()->back()->with('success', 'Club settings updated successfully.');
    }
}
