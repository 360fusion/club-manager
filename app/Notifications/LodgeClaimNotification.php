<?php

namespace App\Notifications;

use App\Mail\LodgeClaimMail;
use App\Models\LodgeClaim;
use Illuminate\Notifications\Notification;

/**
 * Something happened to a lodge claim: a new claim or a reply for the superadmins, a decision or
 * a question for the person who made it. It lands in the bell and in an email.
 */
class LodgeClaimNotification extends Notification
{
    /**
     * @param  string  $route  a named route, without a domain
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $body,
        public readonly string $lodgeName,
        public readonly string $route,
        public readonly array $params = [],
        public readonly string $actionLabel = 'Open',
        public readonly bool $important = false,
    ) {}

    public static function submitted(LodgeClaim $claim): self
    {
        return new self(
            ClubNotification::NOTICE,
            'New lodge claim: '.$claim->lodge->displayName(),
            $claim->user->name.' ('.(LodgeClaim::ROLES[$claim->claimant_role] ?? $claim->claimant_role).') has asked to manage this lodge.',
            $claim->lodge->displayName(),
            'superadmin.lodge_claims.index',
            [],
            'Review the claim',
            true,
        );
    }

    public static function replied(LodgeClaim $claim): self
    {
        return new self(
            ClubNotification::NOTICE,
            'Claim updated: '.$claim->lodge->displayName(),
            $claim->user->name.' has replied to your question about their claim.',
            $claim->lodge->displayName(),
            'superadmin.lodge_claims.index',
            [],
            'Review the claim',
        );
    }

    public static function moreInfoNeeded(LodgeClaim $claim, string $question): self
    {
        return new self(
            ClubNotification::MEMBERSHIP,
            'More information needed for '.$claim->lodge->displayName(),
            $question,
            $claim->lodge->displayName(),
            'members.lodges',
            [],
            'Reply to the question',
            true,
        );
    }

    public static function approved(LodgeClaim $claim): self
    {
        $club = $claim->club;

        return new self(
            ClubNotification::MEMBERSHIP,
            'You now manage '.$claim->lodge->displayName(),
            'Your claim was approved. You can set up members, meetings and the website for '.$club->name.'.',
            $claim->lodge->displayName(),
            'member.dashboard',
            ['slug' => $club->slug],
            'Open your lodge',
            true,
        );
    }

    public static function rejected(LodgeClaim $claim): self
    {
        return new self(
            ClubNotification::MEMBERSHIP,
            'Your claim for '.$claim->lodge->displayName().' was not approved',
            (string) $claim->decision_note,
            $claim->lodge->displayName(),
            'members.lodges',
            [],
            'See your claims',
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'body' => mb_strimwidth(strip_tags($this->body), 0, 200, '…'),
            'important' => $this->important,
            'club' => ['id' => null, 'name' => $this->lodgeName, 'slug' => null],
            'url' => route($this->route, $this->params, false),
        ];
    }

    public function toMail(object $notifiable): LodgeClaimMail
    {
        return (new LodgeClaimMail($this->title, $this->body, $this->actionLabel, route($this->route, $this->params)))
            ->to($notifiable->email);
    }
}
