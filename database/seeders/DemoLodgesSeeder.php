<?php

namespace Database\Seeders;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Enums\EventStatus;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\EventTicketTier;
use App\Models\Meeting;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo lodges and accounts for trying journeys on staging: three units, one account per role, members, meetings,
 * an event with tickets and dishes, and news. Safe to run again: it finds what it made by slug and email.
 *
 * php artisan db:seed --class=DemoLodgesSeeder --force   (needs DEMO_PASSWORD in .env; never runs on production)
 */
class DemoLodgesSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string}> email => first name, last name, rank */
    private const PEOPLE = [
        'demo-master@example.com' => ['Richard', 'Ashworth', 'WBro'],
        'demo-admin@example.com' => ['Simon', 'Carter', 'WBro'],
        'demo-secretary@example.com' => ['Peter', 'Langley', 'WBro'],
        'demo-treasurer@example.com' => ['Graham', 'Fielding', 'WBro'],
        'demo-member@example.com' => ['Daniel', 'Hughes', 'Bro'],
        'demo-new-member@example.com' => ['Oliver', 'Grant', 'Bro'],
        'demo-two-lodges@example.com' => ['James', 'Whitfield', 'WBro'],
        'demo-pending@example.com' => ['Liam', 'Foster', 'Bro'],
        'demo-visitor@example.com' => ['Henry', 'Blake', 'WBro'],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> roster members without an account: first, last, rank, office */
    private const ROSTER = [
        ['Edward', 'Marsh', 'WBro', 'sw'],
        ['Thomas', 'Pryce', 'Bro', 'jw'],
        ['Arthur', 'Kendall', 'WBro', 'chaplain'],
        ['George', 'Whitlock', 'WBro', 'almoner'],
        ['Samuel', 'Okafor', 'Bro', 'sd'],
        ['William', 'Dunmore', 'Bro', 'inner_guard'],
        ['Robert', 'Hale', 'WBro', 'ipm'],
        ['Frank', 'Bellamy', 'WBro', 'tyler'],
        ['Matthew', 'Rowe', 'Bro', 'steward'],
        ['Christopher', 'Ellis', 'Bro', 'member'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo lodges are for staging and local copies only.');
        }

        $password = config('app.demo_password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_PASSWORD (12 characters or more) in .env first.');
        }

        (new ClubTypeSeeder)->run();
        $craft = ClubType::where('code', 'craft_lodge')->firstOrFail();
        $chapterType = ClubType::where('code', 'royal_arch')->firstOrFail();

        $users = [];
        foreach (self::PEOPLE as $email => [$first, $last]) {
            $users[$email] = User::updateOrCreate(['email' => $email], ['name' => "$first $last", 'password' => Hash::make($password)]);
            $users[$email]->forceFill(['email_verified_at' => $users[$email]->email_verified_at ?? now()])->save();
        }

        $harmony = $this->unit('demo-harmony-lodge', 'Demo Harmony Lodge', '9901', $craft, 'April');
        $stAlban = $this->unit('demo-st-albans-lodge', "Demo St Alban's Lodge", '9902', $craft, 'October');
        $chapter = $this->unit('demo-harmony-chapter', 'Demo Harmony Chapter', '9901', $chapterType, 'February');

        // Who is what, by lodge: email => [role, status, office].
        $this->place($harmony, $users, [
            'demo-master@example.com' => [ClubRole::Owner, ClubMemberStatus::Active, LodgeOffice::WorshipfulMaster],
            'demo-admin@example.com' => [ClubRole::Admin, ClubMemberStatus::Active, LodgeOffice::DirectorOfCeremonies],
            'demo-secretary@example.com' => [ClubRole::Coach, ClubMemberStatus::Active, LodgeOffice::Secretary],
            'demo-treasurer@example.com' => [ClubRole::Treasurer, ClubMemberStatus::Active, LodgeOffice::Treasurer],
            'demo-member@example.com' => [ClubRole::Member, ClubMemberStatus::Active, LodgeOffice::JuniorDeacon],
            'demo-new-member@example.com' => [ClubRole::Member, ClubMemberStatus::Active, LodgeOffice::Member],
            'demo-two-lodges@example.com' => [ClubRole::Member, ClubMemberStatus::Active, LodgeOffice::CharitySteward],
            'demo-pending@example.com' => [ClubRole::Member, ClubMemberStatus::Pending, null],
        ]);
        $this->place($stAlban, $users, [
            'demo-two-lodges@example.com' => [ClubRole::Owner, ClubMemberStatus::Active, LodgeOffice::WorshipfulMaster],
            'demo-member@example.com' => [ClubRole::Member, ClubMemberStatus::Active, LodgeOffice::Member],
        ]);
        $this->place($chapter, $users, [
            'demo-master@example.com' => [ClubRole::Owner, ClubMemberStatus::Active, null],
            'demo-two-lodges@example.com' => [ClubRole::Member, ClubMemberStatus::Active, null],
        ]);
        $this->roster($harmony);

        foreach ([$harmony, $stAlban] as $lodge) {
            $this->paymentOptions($lodge);
            $this->meetings($lodge, $lodge->is($stAlban) ? 1 : 0);
        }
        $this->ladiesFestival($harmony);
        $this->news($harmony, $users['demo-secretary@example.com']);
        foreach ([$harmony, $stAlban] as $lodge) {
            ClubPaymentMethod::where('club_id', $lodge->id)->where('is_active', true)->get()->each->offerOnUpcomingEvents();
        }

        $this->command?->info('Demo lodges ready: '.implode(', ', [$harmony->slug, $stAlban->slug, $chapter->slug]));
    }

    private function unit(string $slug, string $name, string $number, ClubType $type, string $installation): Club
    {
        $club = Club::firstOrNew(['slug' => $slug]);
        $club->fill([
            'club_type_id' => $type->id,
            'name' => $name,
            'lodge_number' => $number,
            'town_city' => 'Ashford',
            'status' => 'active',
        ]);
        $club->settings = array_merge($club->settings ?? [], [
            'enabled_modules' => $type->available_modules,
            'currency' => 'GBP',
            'installation_month' => $installation,
            'tagline' => 'A demo lodge for trying Club Manager',
        ]);
        $club->save();

        return $club;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, array{0: ClubRole, 1: ClubMemberStatus, 2: ?LodgeOffice}>  $places
     */
    private function place(Club $club, array $users, array $places): void
    {
        foreach ($places as $email => [$role, $status, $office]) {
            $user = $users[$email];
            $club->users()->syncWithoutDetaching([$user->id => ['role' => $role->value, 'status' => $status->value]]);

            if ($status === ClubMemberStatus::Active) {
                [$first, $last, $rank] = self::PEOPLE[$email];
                Member::updateOrCreate(['club_id' => $club->id, 'user_id' => $user->id], [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                    'masonic_rank' => $rank,
                    'membership_status' => 'active',
                    'current_office' => ($office ?? LodgeOffice::Member)->value,
                    'date_of_initiation' => $email === 'demo-new-member@example.com' ? now()->subMonths(5)->toDateString() : now()->subYears(8)->toDateString(),
                ]);
            }
        }
    }

    private function roster(Club $club): void
    {
        foreach (self::ROSTER as $index => [$first, $last, $rank, $office]) {
            Member::updateOrCreate(['club_id' => $club->id, 'first_name' => $first, 'last_name' => $last], [
                'masonic_rank' => $rank,
                'membership_status' => 'active',
                'current_office' => $office,
                'date_of_initiation' => now()->subYears(4 + $index * 2)->toDateString(),
            ]);
        }
    }

    private function paymentOptions(Club $club): void
    {
        // Made-up bank details: nothing is ever paid into them.
        ClubPaymentMethod::updateOrCreate(['club_id' => $club->id, 'type' => ClubPaymentMethod::BANK], ['label' => 'Bank transfer', 'is_active' => true, 'config' => ['account_name' => $club->name, 'bank_name' => 'Example Bank', 'sort_code' => '00-00-00', 'account_number' => '00000000', 'reference_prefix' => 'DEMO']]);
        ClubPaymentMethod::updateOrCreate(['club_id' => $club->id, 'type' => ClubPaymentMethod::DOOR], ['label' => 'Pay on the night', 'is_active' => true, 'config' => []]);
    }

    private function meetings(Club $club, int $weeksLater): void
    {
        // Made once: the dates are relative to today, so a later run would otherwise add a second set.
        if (Meeting::where('club_id', $club->id)->exists()) {
            return;
        }

        $venue = 'Riverside Masonic Hall, Ashford';
        $next = now()->addWeeks(3 + $weeksLater)->startOfDay();
        $rows = [
            ['Regular Meeting', now()->subWeeks(5 - $weeksLater)->startOfDay(), 'published', true],
            ['Regular Meeting', $next, 'published', true],
            ['Installation Meeting', now()->addWeeks(11 + $weeksLater)->startOfDay(), 'draft', false],
        ];

        foreach ($rows as [$title, $date, $status, $published]) {
            Meeting::create([
                'club_id' => $club->id,
                'meeting_date' => $date->toDateString(),
                'title' => $title,
                'starts_at' => '18:00',
                'rehearsal_starts_at' => '17:00',
                'venue' => $venue,
                'dress_code' => 'Dark suit, white shirt, lodge tie',
                'status' => $status,
                'summons_published_at' => $published ? $date->copy()->subWeeks(2) : null,
                'rsvp_cutoff_at' => $date->copy()->subDays(4),
                'festive_board_menu' => "Leek and potato soup\nRoast chicken with seasonal vegetables\nSticky toffee pudding",
                'dining_cost_member' => 32,
                'dining_cost_guest' => 36,
            ]);
        }
    }

    private function ladiesFestival(Club $club): void
    {
        $starts = now()->addWeeks(7)->setTime(19, 0);
        $event = Event::firstOrNew(['club_id' => $club->id, 'slug' => 'demo-ladies-festival']);
        $event->fill([
            'title' => 'Ladies Festival Dinner',
            'description' => 'An evening of dinner and dancing for members, partners and friends.',
            'location' => 'Riverside Masonic Hall',
            'city' => 'Ashford',
            'starts_at' => $event->starts_at ?? $starts,
            'ends_at' => $event->ends_at ?? $starts->copy()->addHours(5),
            'status' => EventStatus::Upcoming->value,
            'visibility' => 'network',
            'rsvp_audience' => 'network',
            'requires_payment' => true,
            'price' => 55,
            'has_dining' => true,
            'capacity' => 80,
            'waitlist_enabled' => true,
        ])->save();

        foreach ([['Member', 55, 'member'], ['Guest', 55, 'guest']] as [$name, $price, $audience]) {
            EventTicketTier::updateOrCreate(['event_id' => $event->id, 'name' => $name], ['price' => $price, 'max_quantity' => 0, 'audience' => $audience]);
        }
        $dishes = [
            ['starter', 'Chicken liver parfait', false],
            ['starter', 'Roasted tomato soup', true],
            ['main', 'Braised beef with red wine jus', false],
            ['main', 'Wild mushroom risotto', true],
            ['dessert', 'Lemon tart', true],
        ];
        foreach ($dishes as $order => [$category, $name, $vegetarian]) {
            EventMenuItem::updateOrCreate(['event_id' => $event->id, 'name' => $name], ['category' => $category, 'is_vegetarian' => $vegetarian, 'sort_order' => $order]);
        }
    }

    private function news(Club $club, User $author): void
    {
        $post = Post::firstOrNew(['club_id' => $club->id, 'slug' => 'demo-charity-total']);
        $post->fill([
            'author_id' => $author->id,
            'title' => 'Our charity total for the year',
            'excerpt' => 'Thanks to every brother who gave at the festive board.',
            'content' => '<p>Thanks to every brother who gave at the festive board this year.</p>',
            'status' => 'published',
            'visibility' => 'club',
            'published_at' => $post->published_at ?? now()->subDays(3),
        ]);
        // Already announced, so running this never emails anyone about it.
        $post->forceFill(['members_notified_at' => $post->members_notified_at ?? now()])->save();
    }
}
