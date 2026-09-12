<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Donation;
use App\Models\DonationContribution;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\EventPromo;
use App\Models\EventTicketTier;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Models\Newsletter;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClubSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Club Types
        $rowingType = ClubType::create([
            'name' => 'Rowing & Water Sports',
            'code' => 'rowing',
            'available_modules' => ['website_builder', 'donations', 'invoices', 'memberships', 'events', 'dining_and_summons', 'news_and_blog', 'newsletters', 'boat_reservations', 'erg_scores'],
            'default_settings' => [
                'theme' => 'blue',
                'primary_color' => '#0284c7',
            ],
        ]);

        $rugbyType = ClubType::create([
            'name' => 'Rugby & Field Sports',
            'code' => 'rugby',
            'available_modules' => ['website_builder', 'donations', 'invoices', 'memberships', 'events', 'dining_and_summons', 'news_and_blog', 'newsletters', 'pitch_bookings', 'team_fixtures'],
            'default_settings' => [
                'theme' => 'emerald',
                'primary_color' => '#059669',
            ],
        ]);

        // 2. Create Demo Users
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Alex Morgan', 'password' => Hash::make('password')]
        );

        $memberUser = User::firstOrCreate(
            ['email' => 'member@example.com'],
            ['name' => 'Taylor Swift', 'password' => Hash::make('password')]
        );

        $coachUser = User::firstOrCreate(
            ['email' => 'coach@example.com'],
            ['name' => 'Marcus Vance', 'password' => Hash::make('password')]
        );

        $treasurerUser = User::firstOrCreate(
            ['email' => 'treasurer@example.com'],
            ['name' => 'Elena Rostova', 'password' => Hash::make('password')]
        );

        $pendingUser = User::firstOrCreate(
            ['email' => 'pending@example.com'],
            ['name' => 'Jordan Lee', 'password' => Hash::make('password')]
        );

        // 3. Seed Demo Clubs
        $oxfordRowing = Club::create([
            'club_type_id' => $rowingType->id,
            'name' => 'Oxford University Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
            'settings' => [
                'enabled_modules' => ['website_builder', 'donations', 'invoices', 'memberships', 'events', 'dining_and_summons', 'news_and_blog', 'newsletters', 'boat_reservations', 'erg_scores'],
                'tagline' => 'Excellence on the Isis & Thames',
                'primary_color' => '#0369a1',
            ],
        ]);

        $bathRugby = Club::create([
            'club_type_id' => $rugbyType->id,
            'name' => 'Bath RFC Community Club',
            'slug' => 'bath-rfc',
            'status' => 'active',
            'settings' => [
                'enabled_modules' => ['website_builder', 'donations', 'invoices', 'memberships', 'events', 'dining_and_summons', 'news_and_blog', 'newsletters', 'pitch_bookings'],
                'tagline' => 'Home of Blue, Black & White',
                'primary_color' => '#047857',
            ],
        ]);

        // 4. Attach Users
        $oxfordRowing->users()->attach($adminUser->id, ['role' => 'admin', 'member_number' => 'OUBC-001', 'status' => 'active']);
        $oxfordRowing->users()->attach($memberUser->id, ['role' => 'member', 'member_number' => 'OUBC-142', 'status' => 'active']);
        $oxfordRowing->users()->attach($coachUser->id, ['role' => 'coach', 'member_number' => 'OUBC-008', 'status' => 'active']);
        $oxfordRowing->users()->attach($treasurerUser->id, ['role' => 'treasurer', 'member_number' => 'OUBC-015', 'status' => 'active']);
        $oxfordRowing->users()->attach($pendingUser->id, ['role' => 'member', 'member_number' => 'OUBC-999', 'status' => 'pending']);

        // 5. Membership Plans
        MembershipPlan::create([
            'club_id' => $oxfordRowing->id,
            'name' => 'Senior Rower (Full Access)',
            'description' => 'Unlimited boathouse access, erg room, and racing entry.',
            'price' => 45.00,
            'billing_period' => 'monthly',
        ]);

        // 6. Seed Posts (News / Blog)
        Post::create([
            'club_id' => $oxfordRowing->id,
            'author_id' => $adminUser->id,
            'title' => 'Torpids Regatta Lineups & Training Schedule Announced',
            'slug' => 'torpids-regatta-lineups',
            'excerpt' => 'Preparations for the upcoming Isis races are underway with squad trials complete.',
            'content' => 'We are thrilled to publish the official boat assignments for the upcoming Torpids week. Training outings will commence at 06:30 AM daily.',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);

        // 7. Seed Newsletters
        Newsletter::create([
            'club_id' => $oxfordRowing->id,
            'subject' => 'March Newsletter: Annual Dinner & Regatta Updates',
            'content' => 'Dear Members, Please find attached the monthly update regarding our boathouse expansion project.',
            'target_roles' => ['admin', 'member'],
            'status' => 'sent',
            'sent_at' => now()->subDay(),
        ]);

        // 8. Seed Events & Menu Items
        $dinnerEvent = Event::create([
            'club_id' => $oxfordRowing->id,
            'title' => 'Annual Boat Club Dinner & Awards Night',
            'slug' => 'annual-boat-club-dinner-2026',
            'description' => 'Formal black-tie dinner celebrating our regatta victories. 3-course dinner included with ticket.',
            'location' => 'Christ Church Great Hall, Oxford',
            'starts_at' => now()->addDays(14)->setHour(19)->setMinute(0),
            'is_recurring' => false,
            'requires_payment' => true,
            'price' => 35.00,
            'has_dining' => true,
            'dining_price' => 25.00,
            'rsvp_deadline' => now()->addDays(10),
            'status' => 'upcoming',
        ]);

        EventMenuItem::create([
            'event_id' => $dinnerEvent->id,
            'category' => 'starter',
            'name' => 'Smoked Salmon & Caper Tartine',
            'description' => 'Served with dill crème fraîche',
        ]);
        EventMenuItem::create([
            'event_id' => $dinnerEvent->id,
            'category' => 'main',
            'name' => 'Pan-Seared Duck Breast',
            'description' => 'With dauphinoise potatoes & red wine reduction',
        ]);
        EventMenuItem::create([
            'event_id' => $dinnerEvent->id,
            'category' => 'dessert',
            'name' => 'Dark Chocolate Fondant',
            'description' => 'With salted caramel ice cream',
        ]);

        // Seed Eventbrite Ticket Tiers!
        $tierEarly = EventTicketTier::create([
            'event_id' => $dinnerEvent->id,
            'name' => 'Early Bird Admission',
            'price' => 25.00,
            'max_quantity' => 20,
            'sold_quantity' => 12,
        ]);

        $tierGeneral = EventTicketTier::create([
            'event_id' => $dinnerEvent->id,
            'name' => 'General Admission',
            'price' => 35.00,
            'max_quantity' => 100,
            'sold_quantity' => 45,
        ]);

        $tierVip = EventTicketTier::create([
            'event_id' => $dinnerEvent->id,
            'name' => 'VIP Head Table & Sponsor Pass',
            'price' => 60.00,
            'max_quantity' => 15,
            'sold_quantity' => 8,
        ]);

        // Seed Eventbrite Promo Code!
        EventPromo::create([
            'event_id' => $dinnerEvent->id,
            'code' => 'EARLYBIRD10',
            'discount_type' => 'percent',
            'discount_amount' => 10.00, // 10% off
            'max_uses' => 50,
            'uses_count' => 14,
        ]);

        // 9. Seed Custom Website Pages
        Page::create([
            'club_id' => $oxfordRowing->id,
            'title' => 'Welcome to Oxford University Boat Club',
            'slug' => 'home',
            'is_homepage' => true,
            'is_published' => true,
            'show_in_navigation' => true,
            'sort_order' => 1,
            'blocks' => [
                [
                    'type' => 'hero',
                    'title' => 'Training Champion Athletes Since 1839',
                    'subtitle' => 'Official website of Oxford University Boat Club. Join our squads on the Isis & Thames.',
                    'cta_text' => 'Join the Club',
                    'cta_link' => '/pages/join-us',
                ],
                [
                    'type' => 'donation_campaign',
                    'heading' => 'New Empacher 8+ Racing Boat Fund',
                ],
                [
                    'type' => 'events_calendar',
                    'heading' => 'Upcoming Regattas & Formal Dinners',
                ],
            ],
        ]);

        // 10. Seed Donations & Campaign
        $donation = Donation::create([
            'club_id' => $oxfordRowing->id,
            'campaign_name' => 'New Empacher 8+ Racing Boat Fund',
            'target_amount' => 35000.00,
            'current_amount' => 24500.00,
            'description' => 'Help us purchase a custom carbon-fibre Empacher racing shell.',
            'status' => 'active',
        ]);

        // 11. Seed Sample Invoices
        Invoice::create([
            'invoice_number' => 'INV-2026-0001',
            'club_id' => $oxfordRowing->id,
            'user_id' => $memberUser->id,
            'title' => 'Senior Rower Membership Dues (Monthly)',
            'amount' => 45.00,
            'status' => 'paid',
            'paid_at' => now()->subDays(5),
        ]);
    }
}
