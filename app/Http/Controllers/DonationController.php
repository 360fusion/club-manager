<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Donation;
use App\Models\DonationContribution;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    /**
     * Submit a contribution to a fundraising campaign.
     */
    public function contribute(Request $request, string $slug, int $donationId)
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $donation = Donation::where('club_id', $club->id)->findOrFail($donationId);

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_email' => 'required|email|max:255',
            'amount' => 'required|numeric|min:1|max:99999999.99',
        ]);

        $amount = (float) $validated['amount'];

        // Create contribution
        DonationContribution::create([
            'donation_id' => $donation->id,
            'donor_name' => $validated['donor_name'],
            'donor_email' => $validated['donor_email'],
            'amount' => $amount,
            'payment_status' => 'paid',
        ]);

        // Increment campaign total
        $donation->increment('current_amount', $amount);

        if ($donation->current_amount >= $donation->target_amount) {
            $donation->update(['status' => 'completed']);
        }

        return redirect()->back()->with('success', 'Thank you for your generous donation!');
    }
}
