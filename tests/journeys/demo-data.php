<?php

// Demo data for the journey videos. Only ever run against the club_manager_demo database.
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Lodge;
use App\Models\MasonicHall;
use App\Models\User;
use Database\Seeders\ClubTypeSeeder;

abort_unless(DB::connection()->getDatabaseName() === 'club_manager_demo', 500, 'Demo database only');

(new ClubTypeSeeder)->run();
$craft = ClubType::where('code', 'craft_lodge')->firstOrFail();
$club = Club::where('slug', 'lodge-of-fraternity')->firstOrFail();
$club->forceFill([
    'club_type_id' => $craft->id,
    'lodge_number' => '4567',
    'settings' => array_merge($club->settings ?? [], [
        'enabled_modules' => $craft->available_modules,
        'currency' => 'GBP',
        'summons_visibility' => 'members',
    ]),
])->save();

$hall = MasonicHall::firstOrCreate(['name' => 'Riverside Masonic Hall'], ['slug' => 'riverside-masonic-hall', 'address_line_1' => '12 Mill Lane', 'town' => 'Ashford', 'postcode' => 'TN24 8AA', 'country' => 'England']);
$own = Lodge::firstOrCreate(['slug' => 'lodge-of-fraternity-4567'], ['club_type_id' => $craft->id, 'name' => 'Lodge of Fraternity', 'number' => '4567', 'status' => 'active', 'masonic_hall_id' => $hall->id]);
$own->forceFill(['club_id' => $club->id])->save();
foreach ([['Lodge of Unity', '4421'], ["St Aidan's Lodge", '7310'], ['Wealden Lodge', '9012']] as [$name, $number]) {
    Lodge::firstOrCreate(['slug' => Str::slug($name.' '.$number)], ['club_type_id' => $craft->id, 'name' => $name, 'number' => $number, 'status' => 'active', 'masonic_hall_id' => $hall->id]);
}

$people = [
    'admin@example.com' => ['Alex', 'Morgan', 'WBro', 'secretary', 'admin'],
    'treasurer@example.com' => ['Jordan', 'Lee', 'WBro', 'treasurer', 'treasurer'],
    'member@example.com' => ['Thomas', 'Hartley', 'Bro', 'member', 'member'],
    'coach@example.com' => ['David', 'Price', 'Bro', 'member', 'member'],
];
foreach ($people as $email => [$first, $last, $rank, $office, $role]) {
    $user = User::where('email', $email)->firstOrFail();
    $user->forceFill(['name' => "$first $last", 'email_verified_at' => now()])->save();
    $club->users()->updateExistingPivot($user->id, ['role' => $role, 'status' => 'active']);
    Member::updateOrCreate(['club_id' => $club->id, 'user_id' => $user->id], ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'masonic_rank' => $rank, 'membership_status' => 'active', 'current_office' => $office]);
}

ClubPaymentMethod::updateOrCreate(['club_id' => $club->id, 'type' => ClubPaymentMethod::BANK], ['label' => 'Bank transfer', 'is_active' => true, 'config' => ['account_name' => 'Lodge of Fraternity No. 4567', 'bank_name' => 'Example Bank', 'sort_code' => '12-34-56', 'account_number' => '12345678', 'reference_prefix' => 'LF']]);
// Made-up test-mode values so the card option shows; nothing is ever sent to Stripe in the videos.
ClubPaymentMethod::updateOrCreate(['club_id' => $club->id, 'type' => ClubPaymentMethod::CARD], ['label' => 'Card', 'is_active' => true, 'config' => ['stripe_publishable_key' => 'pk_test_demo', 'stripe_secret_key' => 'sk_test_demo', 'stripe_webhook_secret' => 'whsec_demo']]);

BankAccount::updateOrCreate(['club_id' => $club->id, 'account_number' => '12345678'], ['bank_name' => 'Example Bank', 'account_name' => 'Lodge of Fraternity No. 4567', 'account_type' => 'current', 'sort_code' => '12-34-56', 'currency' => 'GBP', 'opening_balance' => 2468.40, 'is_active' => true]);

// Degrees and offices for the Masonic record and the anniversaries page.
Member::where('club_id', $club->id)->where('email', 'member@example.com')->update(['current_office' => 'jd', 'date_of_initiation' => now()->subYears(3)->subMonths(2)->toDateString(), 'date_of_passing' => now()->subYears(3)->toDateString(), 'date_of_raising' => now()->subYears(2)->subMonths(9)->toDateString()]);
Member::where('club_id', $club->id)->where('email', 'coach@example.com')->update(['date_of_initiation' => now()->subYears(50)->addDays(12)->toDateString(), 'date_of_joining' => now()->subYears(21)->addDays(40)->toDateString()]);
Member::where('club_id', $club->id)->where('email', 'admin@example.com')->update(['date_of_initiation' => now()->subYears(18)->addDays(30)->toDateString()]);

$thomas = Member::where('club_id', $club->id)->where('email', 'member@example.com')->first();
$david = Member::where('club_id', $club->id)->where('email', 'coach@example.com')->first();
MemberSubscription::updateOrCreate(['club_id' => $club->id, 'member_id' => $thomas->id, 'billing_year' => 2027], ['due_date' => now()->addDays(10)->toDateString(), 'amount_due' => 180, 'amount_paid' => 0, 'status' => SubscriptionStatus::Unpaid, 'invoice_reference' => 'SUB-2027-014']);
MemberSubscription::updateOrCreate(['club_id' => $club->id, 'member_id' => $david->id, 'billing_year' => 2027], ['due_date' => now()->addMonths(5)->toDateString(), 'amount_due' => 180, 'amount_paid' => 60, 'status' => SubscriptionStatus::PartiallyPaid, 'invoice_reference' => 'SUB-2027-015']);

// No sample events or invoices from the seed: the videos make their own.
Event::query()->each(function ($event) {
    DB::table('event_attendees')->whereIn('event_registration_id', DB::table('event_registrations')->where('event_id', $event->id)->pluck('id'))->delete();
    DB::table('event_registrations')->where('event_id', $event->id)->delete();
    $event->forceDelete();
});

// Only the lodge in the videos: the seed also puts these people in a rugby club.
Club::where('slug', '!=', 'lodge-of-fraternity')->each(fn (Club $other) => $other->users()->detach());

echo "demo data ready\n";
