<?php

namespace App\Support;

use App\Models\Club;
use App\Models\ClubVisitorAccess;
use App\Models\Meeting;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What someone outside a lodge may see of its meetings, and who that is.
 *
 * A lodge picks one of three settings. The default is members only, so nothing is shared until
 * the lodge chooses. What is shared is a fixed short list of facts a visitor needs (date, time,
 * venue, dress and dining). The rest of a summons, such as the sick list, candidates, dues,
 * bank details and officers, is never part of it.
 */
class VisitorSummons
{
    public const MEMBERS = 'members';

    public const APPROVED_VISITORS = 'approved_visitors';

    public const PUBLIC_SAFE = 'public_safe';

    public const VISIBILITIES = [
        self::MEMBERS => 'Members only',
        self::APPROVED_VISITORS => 'Visitors I approve',
        self::PUBLIC_SAFE => 'Anyone: date, time, venue, dress and dining only',
    ];

    public static function visibility(Club $club): string
    {
        $setting = $club->settings['summons_visibility'] ?? self::MEMBERS;

        return array_key_exists($setting, self::VISIBILITIES) ? $setting : self::MEMBERS;
    }

    /**
     * Whether $viewer may see this club's meetings on the public pages.
     */
    public static function canSee(Club $club, ?User $viewer): bool
    {
        if (self::visibility($club) === self::PUBLIC_SAFE) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        if ($viewer->is_super_admin || ClubAccess::isActiveMember($viewer, $club)) {
            return true;
        }

        return self::visibility($club) === self::APPROVED_VISITORS
            && ClubVisitorAccess::where('club_id', $club->id)->where('user_id', $viewer->id)->where('status', ClubVisitorAccess::APPROVED)->exists();
    }

    /**
     * The next published meetings, in the shared form, or nothing when $viewer may not see them.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcoming(Club $club, ?User $viewer, int $limit = 6): array
    {
        if (! self::canSee($club, $viewer)) {
            return [];
        }

        return Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->whereDate('meeting_date', '>=', CarbonImmutable::today()->toDateString())
            ->orderBy('meeting_date')->orderBy('starts_at')
            ->limit($limit)->get()
            ->map(fn (Meeting $meeting) => self::present($meeting, $club))
            ->all();
    }

    /**
     * The only facts about a meeting that leave the lodge. Add to this list with care.
     *
     * @return array<string, mixed>
     */
    public static function present(Meeting $meeting, Club $club): array
    {
        $menu = $meeting->festive_board_menu;

        return [
            'title' => $meeting->title,
            'date' => $meeting->meeting_date->toDateString(),
            'time' => $meeting->starts_at ? substr((string) $meeting->starts_at, 0, 5) : null,
            'venue' => $meeting->venue,
            'dress_code' => $meeting->dress_code,
            'festive_board' => [
                'theme' => $meeting->festive_board_theme,
                'menu' => is_string($menu) ? $menu : null,
                'guest_cost' => $meeting->dining_cost_guest !== null && (float) $meeting->dining_cost_guest > 0
                    ? Currencies::format((float) $meeting->dining_cost_guest, $club)
                    : null,
            ],
            'installation' => $club->isInstallationMeeting($meeting),
        ];
    }
}
