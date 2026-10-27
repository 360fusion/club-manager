<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use App\Enums\Visibility;
use Illuminate\Validation\Rule;

/**
 * Store or update an event (the nested ticket, promo, menu and payment rows are checked in the controller, because a rule on a nested key makes validated() drop its siblings).
 */
class StoreEventRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'location' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:50',
            'starts_at' => 'required|date',
            'requires_payment' => 'boolean',
            'price' => 'nullable|numeric|min:0|max:99999999.99',
            'has_dining' => 'boolean',
            'dining_price' => 'nullable|numeric|min:0|max:99999999.99',
            'booking_cutoff_days' => 'nullable|integer|min:0|max:1000000',
            'status' => ['required', Rule::enum(EventStatus::class)],
            'ends_at' => 'nullable|date|after:starts_at',
            'capacity' => 'nullable|integer|min:1|max:100000',
            'waitlist_enabled' => 'boolean',
            'registration_opens_at' => 'nullable|date',
            'rsvp_deadline' => 'nullable|date',
            'allow_public_registration' => 'boolean',
            'max_guests_per_booking' => 'nullable|integer|min:0|max:50',
            'cancellation_policy' => 'nullable|string|max:5000',
            'booking_fee_type' => 'nullable|in:none,fixed,percent',
            'booking_fee_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'booking_fee_scope' => 'nullable|in:per_booking,per_ticket',
            'booking_fee_label' => 'nullable|string|max:60',
            'price_display' => 'nullable|in:standard,all_in',
            'payment_methods' => 'nullable|array|max:20',
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'rsvp_audience' => ['nullable', Rule::enum(Visibility::class)],
            'ticket_tiers' => 'array|max:100',
            'promos' => 'array|max:100',
            'menu_items' => 'array|max:100',

        ];
    }
}
