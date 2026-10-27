<?php

namespace App\Notifications;

use App\Mail\LodgeClaimMail;
use App\Models\Club;
use App\Models\ClubVisitRequest;
use App\Models\Lodge;
use App\Models\LodgeClaim;
use Illuminate\Notifications\Notification;

/**
 * Something happened to a lodge claim: a new claim or a reply for the superadmins, a decision or
 * a question for the person who made it. It lands in the bell and in an email. Suggested fixes
 * to a listing use it too, and can leave out the email.
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
        public readonly bool $mail = true,
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

    public static function visitRequested(ClubVisitRequest $visit, Club $club): self
    {
        return new self(
            ClubNotification::MEMBERSHIP,
            $visit->user->name.' asks to visit on '.$visit->meeting_date->format('j M Y'),
            'From '.$visit->home_lodge_name.($visit->home_lodge_number ? ' No. '.$visit->home_lodge_number : '').($visit->rank ? ', '.$visit->rank : '').'.'
                .($visit->dining ? ' He would like to dine.' : '').($visit->message ? ' "'.$visit->message.'"' : ''),
            $club->name,
            'admin.visitors.index',
            ['clubSlug' => $club->slug],
            'Answer the request',
            true,
        );
    }

    public static function visitAnswered(ClubVisitRequest $visit, Club $club): self
    {
        $accepted = $visit->status === ClubVisitRequest::ACCEPTED;
        $lodge = $club->lodge;

        return new self(
            ClubNotification::MEMBERSHIP,
            $club->name.($accepted ? ' looks forward to your visit on ' : ' cannot host you on ').$visit->meeting_date->format('j M Y'),
            $visit->reply ?: ($accepted ? 'See the lodge page for the time and venue.' : 'You can ask about another date.'),
            $club->name,
            $lodge ? 'lodges.show' : 'members.lodges',
            $lodge ? ['slug' => $lodge->slug] : [],
            'Open the lodge page',
        );
    }

    /**
     * The yearly question to a managing lodge's officers: is the directory listing still right? A lodge with one admin
     * is also asked to add a second, so it cannot be locked out when that person moves on.
     */
    public static function yearlyCheck(Lodge $lodge, bool $onlyOneAdmin): self
    {
        $details = collect([
            $lodge->meets_text ? 'Meets: '.$lodge->meets_text.'.' : 'No meeting dates are listed.',
            $lodge->masonicHall ? 'Hall: '.$lodge->masonicHall->name.'.' : null,
            'If anything has changed, suggest a fix; if it is all right, press "This listing is right" on the page.',
            $onlyOneAdmin ? 'You are the only admin of '.$lodge->club->name.': please add a second, so the lodge is never locked out.' : null,
        ])->filter()->implode(' ');

        return new self(
            ClubNotification::NOTICE,
            'Is the listing for '.$lodge->displayName().' still right?',
            $details,
            $lodge->displayName(),
            'lodges.show',
            ['slug' => $lodge->slug],
            'Check the listing',
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->mail ? ['database', 'mail'] : ['database'];
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
