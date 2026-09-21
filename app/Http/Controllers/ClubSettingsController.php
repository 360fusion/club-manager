<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Province;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClubSettingsController extends Controller
{
    /**
     * Default Granular Permissions Matrix for Club Roles.
     */
    public static function defaultPermissionMatrix(): array
    {
        return config('club_permissions.capabilities', []);
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
            ['code' => 'meetings', 'name' => 'Meetings & Summonses', 'description' => 'Meeting schedules, nth-weekday rules, summonses, and passwordless RSVPs', 'icon' => '📜'],
            ['code' => 'events', 'name' => 'Events & Ticketing', 'description' => 'Event schedules, ticket tiers, promos, and 3-course dining menus', 'icon' => '🎟️'],
            ['code' => 'dining_and_summons', 'name' => 'Dining & Menu Selections', 'description' => 'Dinner RSVPs, meal choices, and dietary notes', 'icon' => '🍽️'],
            ['code' => 'boat_reservations', 'name' => 'Equipment & Pitch Bookings', 'description' => 'Boat bay, pitch, or facility reservations', 'icon' => '🚣'],
            ['code' => 'erg_scores', 'name' => 'Athletic & Performance Log', 'description' => 'Erg scores, fitness tests, and leaderboards', 'icon' => '📊'],
            ['code' => 'newsletters', 'name' => 'Newsletters & Communications', 'description' => 'Bulk email broadcasts and announcements', 'icon' => '✉️'],
            ['code' => 'donations', 'name' => 'Donations & Fundraising', 'description' => 'Fundraising campaigns and donor receipts', 'icon' => '🎁'],
        ];

        $settings = array_merge([
            // General & Access
            'tagline' => 'Excellence in Club Management',
            'lodge_number' => $club->lodge_number ?? '1418',
            'lodge_status' => 'Normal',
            'installed_masters' => 'No',
            'ritual' => '-',
            'consecration_date' => '3rd April 1873',
            'constitution_date' => '21st October 1872',
            'subscription_month' => 'April',
            'installation_month' => 'April',
            'provincial_ar_month' => 'March',
            'meeting_formula' => '4th Thu. 1 To 11 Ex. 6, 7, 8',
            'primary_color' => '#0369a1',
            'sidebar_theme' => 'dark_slate',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'contact_email' => 'admin@'.$club->slug.'.org',
            'phone' => '+44 20 7946 0912',
            'address' => '100 Boathouse Way, Oxford, Oxfordshire, OX1 1AA, United Kingdom',
            'address_line_1' => '100 Boathouse Way',
            'address_line_2' => '',
            'city' => 'Oxford',
            'county' => 'Oxfordshire',
            'postcode' => 'OX1 1AA',
            'country' => 'United Kingdom',
            'social_facebook' => 'https://facebook.com',
            'social_instagram' => 'https://instagram.com',
            'social_twitter' => 'https://x.com',
            'registration_mode' => 'open',
            'member_prefix' => strtoupper(substr($club->slug, 0, 4)).'-',
            'default_role' => 'member',
            'invite_expiration_days' => 14,
            'membership_year_start' => '2026-10-01',
            'enable_member_ranks' => true,
            'member_ranks' => [
                'Worshipful Master (WM)',
                'Senior Warden (SW)',
                'Junior Warden (JW)',
                'Chaplain (Chap)',
                'Treasurer (Treas)',
                'Secretary (Sec)',
                'Director of Ceremonies (DC)',
                'Almoner (Alm)',
                'Charity Steward (ChStwd)',
                'Membership Officer (MO)',
                'Mentor (Mentor)',
                'Senior Deacon (SD)',
                'Junior Deacon (JD)',
                'Asst Dir of Ceremonies (ADC)',
                'Organist (Org)',
                'Assistant Secretary (ASec)',
                'Inner Guard (IG)',
                'Steward (Stwd)',
                'Tyler (Tyler)',
                'Immediate Past Master (IPM)',
                'Royal Arch Representative (Royal Arch Rep)',
                'Durham FC Representative (Durham FC Rep)',
                'Mentoring & Members Co-ordinator (MenCo-ord)',
            ],

            // Provincial Executive & Officers Roster
            'provincial_name' => 'Provincial Grand Lodge of Durham',
            'provincial_grand_master' => 'R WBro John David Watts',
            'deputy_provincial_grand_master' => 'WBro Andrew Peter Faul Foster PSGD',
            'assistant_provincial_grand_masters' => "WBro Dr. Rakesh Bhalla PSGD\nWBro Thomas Fred Gittins PSGD\nWBro Martin Rankin PJGD\nWBro Michael Stuart Shaw PJGD\nWBro Lt Col John William Henry",
            'officers_year_label' => 'OFFICERS FOR 2025-2026',
            'officers_roster' => [
                ['role' => 'Worshipful Master', 'name' => 'W. Bro. K. D. Lord'],
                ['role' => 'Senior Warden', 'name' => 'Bro. A. Smith'],
                ['role' => 'Junior Warden', 'name' => 'Bro. M. Johnson'],
                ['role' => 'Chaplain', 'name' => 'W. Bro. P. Davies'],
                ['role' => 'Treasurer', 'name' => 'W. Bro. M. Brown'],
                ['role' => 'Secretary', 'name' => 'W. Bro. R. Wilson'],
                ['role' => 'Director of Ceremonies', 'name' => 'W. Bro. T. Anderson'],
                ['role' => 'Almoner', 'name' => 'W. Bro. G. Martin'],
                ['role' => 'Charity Steward', 'name' => 'W. Bro. E. Clark'],
                ['role' => 'Senior Deacon', 'name' => 'Bro. C. White'],
                ['role' => 'Junior Deacon', 'name' => 'Bro. D. Harris'],
                ['role' => 'Assistant Director of Ceremonies', 'name' => 'W. Bro. P. Lewis'],
                ['role' => 'Organist', 'name' => 'Bro. S. Walker'],
                ['role' => 'Assistant Secretary', 'name' => 'Bro. A. Hall'],
                ['role' => 'Inner Guard', 'name' => 'Bro. M. Allen'],
                ['role' => 'Steward', 'name' => 'Bro. J. Young'],
                ['role' => 'Tyler', 'name' => 'Bro. D. King'],
            ],

            // Subscriptions & Dues
            'dues_grace_period_days' => 14,
            'auto_invoice_days_before' => 7,
            'tax_registration_number' => 'GB 987 6543 21',
            'receipt_footer_notes' => 'Thank you for supporting our club. Fees support equipment & clubhouse operations.',

            // Accounting & ERP System Settings
            'fiscal_year_start_month' => 'January',
            'accounting_method' => 'accrual',
            'lock_accounting_date' => '',
            'standard_vat_rate' => 20.0,
            'invoice_prefix' => 'INV-2026-',
            'invoice_due_terms' => 'Net 14',
            'default_ar_account_code' => '1200',
            'default_revenue_account_code' => '4000',
            'bill_prefix' => 'BILL-2026-',
            'default_ap_account_code' => '2000',
            'default_expense_account_code' => '5000',
            'require_bill_approval' => false,
            'default_bank_account_code' => '1000',
            'bank_sort_code' => '20-65-18',
            'bank_account_number' => '83920145',
            'enforce_balanced_journals' => true,

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

        $domainMembers = Member::where('club_id', $club->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $userAccounts = $club->users->keyBy('id');

        $members = $domainMembers->map(function ($m) use ($userAccounts) {
            $user = $m->user_id ? $userAccounts->get($m->user_id) : null;

            return [
                'id' => $user?->id ?? $m->id,
                'user_id' => $m->user_id,
                'member_id' => $m->id,
                'name' => $m->full_name ?: ($user?->name ?? 'Unknown Member'),
                'formatted_rank_name' => $m->formatted_rank_name,
                'email' => $m->email ?: ($user?->email ?? ''),
                'role' => $user?->pivot->role ?? 'member',
                'rank' => $m->masonic_rank ?: ($user?->pivot->rank ?? ''),
                'member_number' => $user?->pivot->member_number ?? '',
                'status' => $m->membership_status?->value ?? ($user?->pivot->status ?? 'active'),
            ];
        });

        $existingUserIds = $domainMembers->pluck('user_id')->filter()->all();
        foreach ($userAccounts as $user) {
            if (! in_array($user->id, $existingUserIds)) {
                $members->push([
                    'id' => $user->id,
                    'user_id' => $user->id,
                    'member_id' => null,
                    'name' => $user->name,
                    'formatted_rank_name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role ?? 'member',
                    'rank' => $user->pivot->rank ?? '',
                    'member_number' => $user->pivot->member_number ?? '',
                    'status' => $user->pivot->status ?? 'active',
                ]);
            }
        }

        $provinces = Province::orderBy('name')->get();

        return Inertia::render('Admin/Settings/Show', [
            'club' => $club->load('province'),
            'settings' => $settings,
            'allModules' => $allModules,
            'members' => $members,
            'provinces' => $provinces,
            'availableRoles' => [
                ['code' => 'owner', 'name' => 'Owner', 'badge' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60'],
                ['code' => 'admin', 'name' => 'Admin', 'badge' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60'],
                ['code' => 'coach', 'name' => 'Coach', 'badge' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60'],
                ['code' => 'treasurer', 'name' => 'Treasurer', 'badge' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800/60'],
                ['code' => 'member', 'name' => 'Member', 'badge' => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border-slate-200 dark:border-slate-800'],
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
            'province_id' => 'nullable|exists:provinces,id',
            'tagline' => 'nullable|string|max:255',
            'lodge_number' => 'nullable|string|max:100',
            'lodge_status' => 'nullable|string|max:100',
            'installed_masters' => 'nullable|string|max:50',
            'ritual' => 'nullable|string|max:255',
            'consecration_date' => 'nullable|string|max:255',
            'constitution_date' => 'nullable|string|max:255',
            'subscription_month' => 'nullable|string|max:100',
            'installation_month' => 'nullable|string|max:100',
            'provincial_ar_month' => 'nullable|string|max:100',
            'meeting_formula' => 'nullable|string|max:255',
            'logo_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
            'primary_color' => 'nullable|string|max:50',
            'sidebar_theme' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:255',
            'social_facebook' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_instagram' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_twitter' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'registration_mode' => 'nullable|in:open,invite_only',
            'member_prefix' => 'nullable|string|max:50',
            'default_role' => 'nullable|in:member,coach,treasurer,admin',
            'invite_expiration_days' => 'nullable|integer|min:1|max:365',
            'membership_year_start' => 'nullable|string|max:50',
            'enable_member_ranks' => 'nullable|boolean',
            'member_ranks' => 'nullable|array|max:100',
            'member_ranks.*' => 'nullable|string|max:255',
            'provincial_name' => 'nullable|string|max:255',
            'provincial_grand_master' => 'nullable|string|max:255',
            'deputy_provincial_grand_master' => 'nullable|string|max:255',
            'assistant_provincial_grand_masters' => 'nullable|string',
            'officers_year_label' => 'nullable|string|max:255',
            'officers_roster' => 'nullable|array|max:100',
            'officers_roster.*.role' => 'nullable|string|max:255',
            'officers_roster.*.name' => 'nullable|string|max:255',
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', Rule::unique('clubs', 'custom_domain')->ignore($club->id)],
            'enabled_modules' => 'nullable|array|max:50',
            'enabled_modules.*' => 'string|max:100',
            'permission_matrix' => 'nullable|array',
            'permission_matrix.*.roles' => 'nullable|array|max:10',
            'permission_matrix.*.roles.*' => ['string', Rule::in(['owner', 'admin', 'coach', 'treasurer', 'member'])],

            // Subscriptions & Dues
            'dues_grace_period_days' => 'nullable|integer|min:0|max:180',
            'auto_invoice_days_before' => 'nullable|integer|min:0|max:90',
            'tax_registration_number' => 'nullable|string|max:100',
            'receipt_footer_notes' => 'nullable|string|max:1000',

            // Accounting & ERP Configuration
            'fiscal_year_start_month' => 'nullable|string|max:50',
            'accounting_method' => 'nullable|in:accrual,cash',
            'lock_accounting_date' => 'nullable|string|max:50',
            'standard_vat_rate' => 'nullable|numeric|min:0|max:100',
            'invoice_prefix' => 'nullable|string|max:50',
            'invoice_due_terms' => 'nullable|string|max:50',
            'default_ar_account_code' => 'nullable|string|max:30',
            'default_revenue_account_code' => 'nullable|string|max:30',
            'bill_prefix' => 'nullable|string|max:50',
            'default_ap_account_code' => 'nullable|string|max:30',
            'default_expense_account_code' => 'nullable|string|max:30',
            'require_bill_approval' => 'nullable|boolean',
            'default_bank_account_code' => 'nullable|string|max:30',
            'bank_sort_code' => 'nullable|string|max:20',
            'bank_account_number' => 'nullable|string|max:30',
            'enforce_balanced_journals' => 'nullable|boolean',

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

        if (array_key_exists('permission_matrix', $validated)) {
            $validated['permission_matrix'] = $this->cleanPermissionMatrix($request, $club, $validated['permission_matrix'] ?? []);
        }

        if (isset($validated['name'])) {
            $club->name = $validated['name'];
        }

        if (array_key_exists('province_id', $validated)) {
            $club->province_id = $validated['province_id'];
        }

        if (isset($validated['logo_url'])) {
            $club->logo_url = $validated['logo_url'];
        }

        if (isset($validated['lodge_number'])) {
            $club->lodge_number = $validated['lodge_number'];
        }

        if (isset($validated['custom_domain']) && $validated['custom_domain'] !== $club->custom_domain) {
            $club->custom_domain = strtolower(trim($validated['custom_domain']));
            $club->domain_status = 'pending';
            $club->domain_verified_at = null;
        }

        $addressParts = array_filter([
            $validated['address_line_1'] ?? null,
            $validated['address_line_2'] ?? null,
            $validated['city'] ?? null,
            $validated['county'] ?? null,
            $validated['postcode'] ?? null,
            $validated['country'] ?? null,
        ]);
        if (! empty($addressParts)) {
            $validated['address'] = implode(', ', $addressParts);
        }

        $existingSettings = $club->settings ?? [];
        $mergedSettings = array_merge($existingSettings, $validated);

        $club->settings = $mergedSettings;
        $club->save();

        return redirect()->back()->with('success', 'Club settings updated successfully.');
    }

    /**
     * Keep only known capabilities with known roles. Only owners (and super
     * admins) may change who holds the settings capability itself, so an admin
     * cannot hand settings control to lower roles or lock others out.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function cleanPermissionMatrix(Request $request, Club $club, array $submitted): array
    {
        $current = $club->settings['permission_matrix'] ?? [];
        $current = is_array($current) ? $current : [];
        $user = $request->user();
        $isOwner = $user->is_super_admin || ClubAccess::role($user, $club) === 'owner';
        $known = array_keys(self::defaultPermissionMatrix());
        $clean = [];

        foreach ($submitted as $capability => $override) {
            if (in_array($capability, $known, true) && is_array($override) && isset($override['roles'])) {
                $clean[$capability] = ['roles' => array_values(array_unique($override['roles']))];
            }
        }

        if (! $isOwner) {
            unset($clean['manage_settings']);

            if (isset($current['manage_settings'])) {
                $clean['manage_settings'] = $current['manage_settings'];
            }
        }

        return $clean;
    }
}
