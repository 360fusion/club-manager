<?php

namespace App\Notifications;

use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\ClubVisitorAccess;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\PassedOnDelivery;
use App\Models\Post;
use App\Models\SignatureRequest;
use App\Support\Currencies;
use App\Support\PassedOnPreferences;
use Illuminate\Notifications\Notification;

/**
 * An in-app notification from a club: a summons, news, an event, a notice or a
 * membership decision. Stored in the `notifications` table for the bell and the
 * notifications page.
 */
class ClubNotification extends Notification
{
    public const SUMMONS = 'summons';

    public const NEWS = 'news';

    public const EVENT = 'event';

    public const NOTICE = 'notice';

    public const MEMBERSHIP = 'membership';

    public const PAYMENT = 'payment';

    public const SIGNATURE = 'signature';

    public const REMINDER = 'reminder';

    /** Passed on from another lodge, Province or Grand Lodge: the member's own choices (PassedOnPreferences) decide it, not push_muted. */
    public const PASSED_ON = 'passed_on';

    /** The kinds someone may stop being sent to their phone; payments, signatures and membership decisions always are. */
    public const PUSH_OPTIONAL = [self::SUMMONS, self::EVENT, self::NEWS, self::NOTICE, self::REMINDER];

    /**
     * @param  array<string, mixed>  $params  route parameters for the link
     */
    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $body,
        public readonly Club $club,
        public readonly string $route,
        public readonly array $params = [],
        public readonly bool $important = false,
    ) {}

    public static function summons(Meeting $meeting, Club $club): self
    {
        return new self(
            self::SUMMONS,
            'New summons: '.$meeting->title,
            trim($meeting->meeting_date->format('j M Y').' · '.($meeting->venue ?? ''), ' ·'),
            $club,
            'member.meetings.summons',
            ['slug' => $club->slug, 'id' => $meeting->id],
            true,
        );
    }

    public static function passedOn(PassedOnDelivery $delivery): self
    {
        $item = $delivery->item;

        return new self(
            self::PASSED_ON,
            $item->title,
            implode(' · ', array_filter([$item->lodge?->displayName() ?? (PassedOnPreferences::LABELS[$delivery->kind] ?? null), $item->meeting_date?->format('j M Y')])),
            $delivery->viaClub,
            'members.passed_on.show',
            ['delivery' => $delivery->id],
        );
    }

    public static function visitorRequested(ClubVisitorAccess $access, Club $club): self
    {
        return new self(
            self::MEMBERSHIP,
            $access->user->name.' asks to receive your summonses',
            'From '.$access->home_lodge_name.($access->home_lodge_number ? ' No. '.$access->home_lodge_number : '').'.',
            $club,
            'admin.visitors.index',
            ['clubSlug' => $club->slug],
            true,
        );
    }

    public static function visitorDecided(ClubVisitorAccess $access, Club $club): self
    {
        $approved = $access->status === ClubVisitorAccess::APPROVED;
        $lodge = $club->lodge;

        return new self(
            self::MEMBERSHIP,
            $approved ? $club->name.' will share its summonses with you' : $club->name.' did not approve your request',
            $approved ? 'You can now see its meetings on its page.' : 'You can ask again later.',
            $club,
            $lodge ? 'lodges.show' : 'members.dashboard',
            $lodge ? ['slug' => $lodge->slug] : [],
        );
    }

    /**
     * For someone who follows a lodge without belonging to it.
     */
    public static function followedSummons(Meeting $meeting, Club $club, string $lodgeSlug): self
    {
        return new self(
            self::SUMMONS,
            'New summons: '.$meeting->title,
            trim($meeting->meeting_date->format('j M Y').' · '.($meeting->venue ?? ''), ' ·'),
            $club,
            'lodges.show',
            ['slug' => $lodgeSlug],
        );
    }

    public static function news(Post $post, Club $club): self
    {
        return new self(self::NEWS, $post->title, $post->excerpt ?: '', $club, 'member.posts.show', ['slug' => $club->slug, 'id' => $post->id]);
    }

    public static function event(Event $event, Club $club): self
    {
        return new self(
            self::EVENT,
            'New event: '.$event->title,
            trim($event->starts_at->format('j M Y, H:i').' · '.($event->location ?? ''), ' ·'),
            $club,
            'member.events',
            ['slug' => $club->slug],
        );
    }

    public static function payment(EventRegistration $registration, Club $club): self
    {
        $due = $registration->due_at;

        return new self(
            self::PAYMENT,
            'Payment due: '.$registration->event->title,
            Currencies::format($registration->balanceDue(), $club).($due ? ' to pay by '.$due->format('j M Y') : ' still to pay'),
            $club,
            'member.events',
            ['slug' => $club->slug],
            true,
        );
    }

    /** Dues behind, overdue or nearly due (app:send-dues-reminders), opening the member's dues page. */
    public static function dues(Club $club, string $left, string $message): self
    {
        return new self(self::PAYMENT, 'Dues: '.$left.' still to pay', $message, $club, 'members.dues', [], true);
    }

    /** A step of a meeting's reply schedule, opening the member's summons where they can reply or change it. */
    public static function meetingReply(Meeting $meeting, Club $club, string $title, bool $reminder): self
    {
        return new self(
            $reminder ? self::REMINDER : self::SUMMONS,
            $title,
            trim($meeting->meeting_date->format('j M Y').' · '.($meeting->venue ?? ''), ' ·'),
            $club,
            'member.meetings.summons',
            ['slug' => $club->slug, 'id' => $meeting->id],
        );
    }

    /** The day before an event someone has booked: when and where, and their ticket one tap away. */
    public static function eventTomorrow(EventRegistration $registration, Club $club): self
    {
        $event = $registration->event;

        return new self(
            self::REMINDER,
            'Tomorrow: '.$event->title,
            trim($event->starts_at->format('H:i').' · '.($event->formatted_location ?: $event->location ?? ''), ' ·'),
            $club,
            'member.events.ticket',
            ['slug' => $club->slug, 'id' => $event->id],
        );
    }

    public static function notice(ClubUpdate $update, Club $club): self
    {
        return new self(self::NOTICE, $update->title, (string) $update->summary, $club, 'members.dashboard', [], (bool) $update->is_important);
    }

    public static function membershipApproved(Club $club): self
    {
        return new self(self::MEMBERSHIP, 'Welcome to '.$club->name, 'Your membership has been approved.', $club, 'member.dashboard', ['slug' => $club->slug]);
    }

    public static function signature(SignatureRequest $request): self
    {
        $club = $request->club;

        return new self(
            self::SIGNATURE,
            'Signature needed: '.$request->signable->signatureLabel($request->purpose),
            'Requested by '.($request->requestedBy?->name ?? $club->name).'.',
            $club,
            'member.signatures.show',
            ['slug' => $club->slug, 'id' => $request->id],
            true,
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
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
            'club' => ['id' => $this->club->id, 'name' => $this->club->name, 'slug' => $this->club->slug],
            'url' => route($this->route, $this->params, false),
        ];
    }
}
