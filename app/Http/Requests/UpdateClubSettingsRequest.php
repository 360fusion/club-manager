<?php

namespace App\Http\Requests;

use App\Support\ClubDomain;
use App\Support\Currencies;
use App\Support\VisitorSummons;
use Illuminate\Validation\Rule;

/**
 * Update a club's settings.
 */
class UpdateClubSettingsRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'name' => 'sometimes|required|string|max:255',
            'province_id' => 'nullable|exists:provinces,id',
            'masonic_hall_id' => 'nullable|exists:masonic_halls,id',
            'tagline' => 'nullable|string|max:255',
            'lodge_number' => 'nullable|string|max:100',
            'lodge_status' => 'nullable|string|max:100',
            'installed_masters' => 'nullable|string|max:50',
            'ritual' => 'nullable|string|max:255',
            'consecration_date' => 'nullable|string|max:255',
            'constitution_date' => 'nullable|string|max:255',
            'subscription_month' => 'nullable|string|max:100',
            'installation_month' => 'nullable|string|max:100',
            'summons_visibility' => ['nullable', Rule::in(array_keys(VisitorSummons::VISIBILITIES))],
            'provincial_ar_month' => 'nullable|string|max:100',
            'meeting_formula' => 'nullable|string|max:255',
            'logo_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
            'primary_color' => 'nullable|string|max:50',
            'sidebar_theme' => 'nullable|string|max:50',
            'currency' => ['nullable', 'string', 'size:3', Rule::in(array_keys(Currencies::available()))],
            'timezone' => 'nullable|string|max:100',
            'storage_quota_mb' => 'nullable|integer|min:0',
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
            'invite_reminder_days' => 'nullable|integer|min:0|max:365',
            'membership_year_start' => 'nullable|string|max:50',
            'enable_member_ranks' => 'nullable|boolean',
            'member_ranks' => 'nullable|array|max:100',
            'member_ranks.*' => 'nullable|string|max:255',
            'grand_ranks' => 'nullable|array|max:200',
            'grand_ranks.*.abbreviation' => 'required|string|max:30|distinct:ignore_case',
            'grand_ranks.*.title' => 'nullable|string|max:120',
            'provincial_ranks' => 'nullable|array|max:200',
            'provincial_ranks.*.abbreviation' => 'required|string|max:30|distinct:ignore_case',
            'provincial_ranks.*.title' => 'nullable|string|max:120',
            'provincial_name' => 'nullable|string|max:255',
            'provincial_grand_master' => 'nullable|string|max:255',
            'deputy_provincial_grand_master' => 'nullable|string|max:255',
            'assistant_provincial_grand_masters' => 'nullable|string|max:10000',
            'officers_year_label' => 'nullable|string|max:255',
            'officers_roster' => 'nullable|array|max:100',
            'officers_roster.*.role' => 'nullable|string|max:255',
            'officers_roster.*.name' => 'nullable|string|max:255',
            'custom_domain' => ClubDomain::rule($club),
            'enabled_modules' => 'nullable|array|max:50',
            'enabled_modules.*' => 'string|max:100',
            'permission_matrix' => 'nullable|array|max:100',
            'permission_matrix.*.roles' => 'nullable|array|max:10',
            'permission_matrix.*.roles.*' => ['string', Rule::in(['owner', 'admin', 'coach', 'treasurer', 'member', 'examiner'])],

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

        ];
    }
}
