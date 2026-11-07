<?php

namespace App\Observers;

use App\Models\DonationContribution;
use App\Support\ClubTeamEvents;

/** A donation received: a contribution saved as paid, or marked paid later */
class DonationContributionTeamEvents
{
    public function created(DonationContribution $contribution): void
    {
        $contribution->payment_status === 'paid' && $this->record($contribution);
    }

    public function updated(DonationContribution $contribution): void
    {
        $contribution->wasChanged('payment_status') && $contribution->payment_status === 'paid' && $this->record($contribution);
    }

    private function record(DonationContribution $contribution): void
    {
        $donation = $contribution->donation;

        if (! $donation) {
            return;
        }

        ClubTeamEvents::record((int) $donation->club_id, 'donation.received', "recorded a donation from {$contribution->donor_name} to {$donation->campaign_name}", [
            'donation_id' => $donation->id, 'contribution_id' => $contribution->id, 'donor_name' => $contribution->donor_name, 'amount' => $contribution->amount,
        ]);
    }
}
