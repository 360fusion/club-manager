<?php

namespace App\Services\Newsletters;

use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscription;

/**
 * Leaving a newsletter channel from the link in an email. Members cannot leave a mandatory channel (official
 * lodge notices), and a newsletter with no channel has nothing to leave.
 */
class NewsletterOptOut
{
    public function canOptOut(NewsletterDelivery $delivery): bool
    {
        $delivery->loadMissing('newsletter.newsletterType', 'newsletter.club');
        $type = $delivery->newsletter->newsletterType;

        if (! $type) {
            return false;
        }

        $isMember = $delivery->user_id !== null && $delivery->newsletter->club->users()->wherePivot('status', 'active')->where('users.id', $delivery->user_id)->exists();

        return ! ($isMember && $type->is_mandatory);
    }

    /**
     * @return bool whether they are now unsubscribed
     */
    public function apply(NewsletterDelivery $delivery): bool
    {
        if (! $this->canOptOut($delivery)) {
            return false;
        }

        $type = $delivery->newsletter->newsletterType;
        $query = NewsletterSubscription::where('club_id', $delivery->club_id)->where('newsletter_type_id', $type->id);
        $row = $delivery->user_id ? (clone $query)->where('user_id', $delivery->user_id)->first() : (clone $query)->where('email', $delivery->email)->first();

        if ($row) {
            $row->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        } else {
            NewsletterSubscription::create([
                'club_id' => $delivery->club_id,
                'newsletter_type_id' => $type->id,
                'user_id' => $delivery->user_id,
                'email' => $delivery->email,
                'name' => $delivery->name,
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);
        }

        return true;
    }

    public function resubscribe(NewsletterDelivery $delivery): void
    {
        $type = $delivery->newsletter->newsletterType;

        if ($type) {
            NewsletterSubscription::where('club_id', $delivery->club_id)->where('newsletter_type_id', $type->id)
                ->where(fn ($q) => $delivery->user_id ? $q->where('user_id', $delivery->user_id) : $q->where('email', $delivery->email))
                ->update(['status' => 'active', 'subscribed_at' => now(), 'unsubscribed_at' => null]);
        }
    }
}
