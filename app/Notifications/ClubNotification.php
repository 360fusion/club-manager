<?php

namespace App\Notifications;

use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\Post;
use App\Models\SignatureRequest;
use App\Support\Currencies;
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
            trim(($meeting->meeting_date?->format('j M Y') ?? '').' · '.($meeting->venue ?? ''), ' ·'),
            $club,
            'member.meetings.summons',
            ['slug' => $club->slug, 'id' => $meeting->id],
            true,
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
            trim(($event->starts_at?->format('j M Y, H:i') ?? '').' · '.($event->location ?? ''), ' ·'),
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
