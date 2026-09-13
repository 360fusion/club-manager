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
            'view_dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Access analytics dashboard, revenue reports, and attendance statistics',
                'roles' => ['owner', 'admin', 'treasurer'],
            ],
            'manage_events' => [
                'label' => 'Events',
                'description' => 'Create, edit, and publish events and attendance check-ins',
                'roles' => ['owner', 'admin', 'coach'],
            ],
            'manage_subscriptions' => [
                'label' => 'Subscriptions',
                'description' => 'Configure membership dues tiers, pricing, and recurring subscription plans',
                'roles' => ['owner', 'admin', 'treasurer'],
            ],
            'manage_members' => [
                'label' => 'Members',
                'description' => 'View member roster, approve new join applications, and manage member roles',
                'roles' => ['owner', 'admin'],
            ],
            'manage_billing' => [
                'label' => 'Billing',
                'description' => 'Access payment history, issue receipts, and manage club platform billing',
                'roles' => ['owner', 'admin', 'treasurer'],
            ],
            'manage_communications' => [
                'label' => 'Communications',
                'description' => 'Create and publish community announcements, posts, and news updates',
                'roles' => ['owner', 'admin', 'coach'],
            ],
            'send_newsletters' => [
                'label' => 'Newsletters',
                'description' => 'Draft, preview, and dispatch bulk email newsletters to members',
                'roles' => ['owner', 'admin', 'coach'],
            ],
            'edit_website' => [
                'label' => 'Website Builder',
                'description' => 'Build CMS pages, customize navigation, and update site content',
                'roles' => ['owner', 'admin'],
            ],
            'manage_settings' => [
                'label' => 'Club Settings',
                'description' => 'Configure organization profile, branding, custom domain, and permission matrix',
                'roles' => ['owner', 'admin'],
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
            ['code' => 'events', 'name' => 'Events', 'description' => 'Event schedules, RSVPs, and attendance check-in', 'icon' => '📅'],
            ['code' => 'dining_and_summons', 'name' => 'Dining & Menu Selections', 'description' => 'Dinner RSVPs, meal choices, and dietary notes', 'icon' => '🍽️'],
            ['code' => 'boat_reservations', 'name' => 'Equipment & Pitch Bookings', 'description' => 'Boat bay, pitch, or facility reservations', 'icon' => '🚣'],
            ['code' => 'erg_scores', 'name' => 'Athletic & Performance Log', 'description' => 'Erg scores, fitness tests, and leaderboards', 'icon' => '📊'],
            ['code' => 'newsletters', 'name' => 'Newsletters & Communications', 'description' => 'Bulk email broadcasts and announcements', 'icon' => '✉️'],
            ['code' => 'donations', 'name' => 'Donations & Fundraising', 'description' => 'Fundraising campaigns and donor receipts', 'icon' => '🎁'],
        ];

        $settings = array_merge([
            // General & Access
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
            'invite_expiration_days' => 14,
            'enable_member_ranks' => true,
            'member_ranks' => ['Novice', 'Intermediate', 'Senior', 'Captain', 'Coxswain', 'Veteran'],

            // Subscriptions & Dues
            'dues_grace_period_days' => 14,
            'auto_invoice_days_before' => 7,
            'tax_registration_number' => 'GB 987 6543 21',
            'receipt_footer_notes' => 'Thank you for supporting our club. Fees support equipment & clubhouse operations.',

            // Events & Check-Ins
            'event_rsvp_cutoff_hours' => 24,
            'max_guests_per_member' => 2,
            'qr_code_expiry_minutes' => 60,
            'notify_event_reminders' => true,

            // Dining & Catering
            'dining_rsvp_cutoff_hours' => 48,
            'require_dietary_allergens' => true,
            'allow_guest_meals' => true,

            // Newsletters & Communications
            'email_from_name' => $club->name,
            'email_reply_to' => 'admin@'.$club->slug.'.org',
            'email_footer_address' => '100 Boathouse Way, Oxford, UK',
            'notify_dues_overdue' => true,

            // Website Builder & SEO
            'seo_title_suffix' => '| '.$club->name,
            'seo_meta_description' => 'Official hub and member portal for '.$club->name,
            'custom_domain' => $club->custom_domain,

            // Equipment & Pitch Bookings
            'booking_window_days' => 14,
            'max_booking_hours' => 4,
            'require_coach_approval_equipment' => true,

            // Athletic Performance & Erg Scores
            'leaderboard_visibility' => 'public',
            'default_distance_unit' => 'meters',
            'require_score_verification' => false,

            'enabled_modules' => $club->clubType->available_modules ?? [],
            'permission_matrix' => self::defaultPermissionMatrix(),
        ], $club->settings ?? []);

        // Ensure permission matrix has all navigation page keys
        $settings['permission_matrix'] = array_merge(
            self::defaultPermissionMatrix(),
            $club->settings['permission_matrix'] ?? []
        );

        $members = $club->users->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role ?? 'member',
            'rank' => $u->pivot->rank ?? '',
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
            'invite_expiration_days' => 'nullable|integer|min:1|max:365',
            'enable_member_ranks' => 'nullable|boolean',
            'member_ranks' => 'nullable|array',
            'custom_domain' => 'nullable|string|max:255',
            'enabled_modules' => 'nullable|array',
            'permission_matrix' => 'nullable|array',

            // Subscriptions & Dues
            'dues_grace_period_days' => 'nullable|integer|min:0|max:180',
            'auto_invoice_days_before' => 'nullable|integer|min:0|max:90',
            'tax_registration_number' => 'nullable|string|max:100',
            'receipt_footer_notes' => 'nullable|string|max:1000',

            // Events & Check-Ins
            'event_rsvp_cutoff_hours' => 'nullable|integer|min:0|max:168',
            'max_guests_per_member' => 'nullable|integer|min:0|max:20',
            'qr_code_expiry_minutes' => 'nullable|integer|min:5|max:1440',
            'notify_event_reminders' => 'nullable|boolean',

            // Dining & Catering
            'dining_rsvp_cutoff_hours' => 'nullable|integer|min:0|max:168',
            'require_dietary_allergens' => 'nullable|boolean',
            'allow_guest_meals' => 'nullable|boolean',

            // Newsletters & Communications
            'email_from_name' => 'nullable|string|max:255',
            'email_reply_to' => 'nullable|email|max:255',
            'email_footer_address' => 'nullable|string|max:500',
            'notify_dues_overdue' => 'nullable|boolean',

            // Website Builder & SEO
            'seo_title_suffix' => 'nullable|string|max:255',
            'seo_meta_description' => 'nullable|string|max:500',

            // Equipment & Pitch Bookings
            'booking_window_days' => 'nullable|integer|min:1|max:365',
            'max_booking_hours' => 'nullable|integer|min:1|max:24',
            'require_coach_approval_equipment' => 'nullable|boolean',

            // Athletic Performance & Erg Scores
            'leaderboard_visibility' => 'nullable|in:public,private,coaches_only',
            'default_distance_unit' => 'nullable|string|max:50',
            'require_score_verification' => 'nullable|boolean',
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
