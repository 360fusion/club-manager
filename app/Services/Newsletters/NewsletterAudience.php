<?php

namespace App\Services\Newsletters;

use App\Models\Newsletter;
use App\Models\NewsletterSubscription;
use Illuminate\Support\Collection;

/**
 * Who a newsletter goes to.
 *
 * - Members: active members of the club in the roles the newsletter targets. A channel's members can opt out
 *   (an unsubscribed row), except on a mandatory channel, and a newsletter with no channel counts as mandatory.
 * - Visitors: active subscribers to the channel who are not members of the club.
 * Each address appears once. People waiting for approval, rejected or unsubscribed are never included.
 */
class NewsletterAudience
{
    /**
     * @return Collection<int, array{email: string, name: ?string, user_id: ?int, subscription_id: ?int, visitor: bool}>
     */
    public function recipients(Newsletter $newsletter): Collection
    {
        $club = $newsletter->club;
        $type = $newsletter->newsletterType;
        $roles = $newsletter->target_roles ?: ['member'];
        $mandatory = $type === null || $type->is_mandatory;

        $rows = NewsletterSubscription::where('club_id', $club->id)
            ->when($type, fn ($q) => $q->where('newsletter_type_id', $type->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->get();

        $optedOut = $rows->where('status', 'unsubscribed')->whereNotNull('user_id')->pluck('user_id')->all();

        $members = $club->users()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', $roles)
            ->get()
            ->reject(fn ($user) => ! $mandatory && in_array($user->id, $optedOut, true))
            ->map(fn ($user) => ['email' => $user->email, 'name' => $user->name, 'user_id' => $user->id, 'subscription_id' => $rows->firstWhere('user_id', $user->id)?->id, 'visitor' => false]);

        $memberIds = $club->users()->wherePivot('status', 'active')->pluck('users.id')->all();

        $visitors = $rows->where('status', 'active')
            ->reject(fn (NewsletterSubscription $row) => $row->user_id !== null && in_array($row->user_id, $memberIds, true))
            ->map(fn (NewsletterSubscription $row) => ['email' => $row->email, 'name' => $row->name, 'user_id' => $row->user_id, 'subscription_id' => $row->id, 'visitor' => true]);

        return $members->concat($visitors)
            ->filter(fn (array $person) => filter_var($person['email'], FILTER_VALIDATE_EMAIL))
            ->unique(fn (array $person) => strtolower($person['email']))
            ->values();
    }

    /**
     * How many people it would reach, for showing beside a draft.
     *
     * @return array{total: int, members: int, visitors: int}
     */
    public function counts(Newsletter $newsletter): array
    {
        $recipients = $this->recipients($newsletter);
        $visitors = $recipients->where('visitor', true)->count();

        return ['total' => $recipients->count(), 'members' => $recipients->count() - $visitors, 'visitors' => $visitors];
    }
}
