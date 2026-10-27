<?php

namespace App\Services;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Http\Controllers\ClubSettingsController;
use App\Models\Club;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Support\Currencies;
use App\Support\MasonicRanks;

/**
 * Everything the club settings screen is given: the club with its relations, the settings with their defaults, the
 * modules, roles, provinces, halls, currencies and storage figures.
 */
class ClubSettingsScreenData
{
    /**
     * @return array<string, mixed>
     */
    public function props(Club $club): array
    {
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
            'lodge_number' => $club->lodge_number ?? '',
            'lodge_status' => 'Normal',
            'installed_masters' => 'No',
            'ritual' => '-',
            'consecration_date' => '',
            'constitution_date' => '',
            'subscription_month' => 'April',
            'installation_month' => 'April',
            'provincial_ar_month' => 'March',
            'meeting_formula' => '',
            'primary_color' => '#0369a1',
            'sidebar_theme' => 'dark_slate',
            'currency' => $club->currencyCode(),
            'timezone' => 'Europe/London',
            'storage_quota_mb' => null,
            'contact_email' => 'admin@'.$club->slug.'.org',
            'phone' => '',
            'address' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'county' => '',
            'postcode' => '',
            'country' => 'United Kingdom',
            'social_facebook' => '',
            'social_instagram' => '',
            'social_twitter' => '',
            'registration_mode' => 'open',
            'member_prefix' => strtoupper(substr($club->slug, 0, 4)).'-',
            'default_role' => 'member',
            'invite_expiration_days' => 14,
            'invite_reminder_days' => 7,
            'membership_year_start' => '',
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
                'Mentoring & Members Co-ordinator (MenCo-ord)',
            ],

            // Provincial Executive & Officers Roster
            'provincial_name' => '',
            'provincial_grand_master' => '',
            'deputy_provincial_grand_master' => '',
            'assistant_provincial_grand_masters' => '',
            'officers_year_label' => '',
            'officers_roster' => [],

            // Subscriptions & Dues
            'dues_grace_period_days' => 14,
            'auto_invoice_days_before' => 7,
            'tax_registration_number' => '',
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
            'bank_sort_code' => '',
            'bank_account_number' => '',
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
            'email_footer_address' => '',
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
            'permission_matrix' => ClubSettingsController::defaultPermissionMatrix(),
        ], $club->settings ?? []);

        // Always show the currency actually in use, even if the stored value is missing or unknown.
        $settings['currency'] = $club->currencyCode();

        // Ensure permission matrix has all navigation page keys
        $settings['permission_matrix'] = array_merge(
            ClubSettingsController::defaultPermissionMatrix(),
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
                // The lodge office (Worshipful Master, Secretary, ...) is owned by the annual
                // officer roster (see admin.officers.*), never editable from here.
                'current_office_label' => $m->current_office && $m->current_office !== LodgeOffice::Member
                    ? $m->current_office->label()
                    : null,
                'current_office_badge_class' => $m->current_office?->badgeClass(),
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
                    'current_office_label' => null,
                    'current_office_badge_class' => null,
                ]);
            }
        }

        $provinces = Province::orderBy('name')->get();

        // The lodge's rank lists, with how many members hold each rank so the editor can say so.
        $settings['grand_ranks'] = MasonicRanks::forClub($club, MasonicRanks::GRAND);
        $settings['provincial_ranks'] = MasonicRanks::forClub($club, MasonicRanks::PROVINCIAL);
        $rankUsage = fn (string $column) => Member::where('club_id', $club->id)->whereNotNull($column)->selectRaw("{$column} as rank_value, count(*) as total")->groupBy($column)->pluck('total', 'rank_value')->all();

        return [
            'ranks' => [
                'grandLodgeName' => $club->province?->grandLodge?->name,
                'usage' => ['grand' => $rankUsage('grand_rank'), 'provincial' => $rankUsage('provincial_rank')],
            ],
            'club' => $club->load('province'),
            'newsTags' => $club->newsTags()->withCount('posts')->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'settings' => $settings,
            'allModules' => $allModules,
            'members' => $members,
            'provinces' => $provinces,
            'masonicHalls' => MasonicHall::orderBy('name')->get(['id', 'province_id', 'name', 'kind', 'address_line_1', 'address_line_2', 'town', 'county', 'postcode']),
            'currencies' => array_values(Currencies::available()),
            'currencyLocked' => $club->currencyIsLocked(),
            'storage' => $club->storageSummary(),
            'platformDefaultStorageQuotaMb' => config('club_media.default_storage_quota_mb'),
            'availableRoles' => [
                ['code' => 'owner', 'name' => 'Owner', 'badge' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60'],
                ['code' => 'admin', 'name' => 'Admin', 'badge' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60'],
                ['code' => 'coach', 'name' => 'Secretary', 'badge' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60'],
                ['code' => 'treasurer', 'name' => 'Treasurer', 'badge' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800/60'],
                ['code' => 'member', 'name' => 'Media Manager', 'badge' => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border-slate-200 dark:border-slate-800'],
                ['code' => 'examiner', 'name' => 'Examiner / Auditor', 'badge' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200 border-purple-200 dark:border-purple-800/60'],
            ],
        ];
    }
}
