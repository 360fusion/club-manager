<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Enums\Visibility;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentLog;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private EventRegistration $registration;

    private User $treasurer;

    private BankImport $import;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->treasurer = User::factory()->create();
        $this->club->users()->attach($this->treasurer->id, ['role' => 'treasurer', 'status' => 'active']);

        $event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(10), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 50, 'booking_fee_type' => 'fixed', 'booking_fee_amount' => 2]);
        $method = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'bank_transfer', 'label' => 'Bank', 'config' => ['reference_prefix' => 'GALA']]);
        $option = EventPaymentMethod::create(['event_id' => $event->id, 'payment_method_id' => $method->id, 'is_enabled' => true]);
        $member = User::factory()->create(['name' => 'Mia Smithson']);
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $this->registration = app(EventRegistrationService::class)->register($event, $member, ['payment_method' => $option->id, 'attendees' => [['name' => 'Mia Smithson']]]);
        $this->import = BankImport::create(['club_id' => $this->club->id, 'filename' => 's.csv', 'total_lines' => 1, 'total_amount' => 52]);
    }

    private function line(string $description, float $amount): BankTransaction
    {
        return BankTransaction::create(['club_id' => $this->club->id, 'bank_import_id' => $this->import->id, 'transaction_date' => '2026-10-12', 'raw_description' => $description, 'amount' => $amount, 'transaction_hash' => md5($description.$amount), 'status' => BankTransactionStatus::Unmatched]);
    }

    private function matcher(): BankReconciliationMatcherService
    {
        return app(BankReconciliationMatcherService::class);
    }

    public function test_a_payment_quoting_the_booking_reference_is_suggested_first(): void
    {
        $this->assertSame('52.00', $this->registration->total);

        $suggestions = $this->matcher()->suggestMatches($this->line('FASTER PAYMENT MIA S '.$this->registration->payment_reference, 52.00));

        $this->assertSame('event_registration', $suggestions[0]['match_type']);
        $this->assertSame($this->registration->id, $suggestions[0]['target_id']);
        $this->assertSame(100, $suggestions[0]['confidence_score']);
    }

    public function test_the_name_and_exact_amount_are_a_weaker_match_and_a_wrong_amount_is_not_suggested(): void
    {
        $byName = $this->matcher()->suggestMatches($this->line('BACS Smithson dinner', 52.00));
        $this->assertSame('event_registration', $byName[0]['match_type']);
        $this->assertSame(80, $byName[0]['confidence_score']);

        $tooMuch = $this->matcher()->suggestMatches($this->line('BACS '.$this->registration->payment_reference, 500.00));
        $this->assertNotContains('event_registration', array_column($tooMuch, 'match_type'));

        $part = $this->matcher()->suggestMatches($this->line('BACS '.$this->registration->payment_reference, 20.00));
        $this->assertSame(90, $part[0]['confidence_score'], 'a part payment quoting the reference still matches');
    }

    public function test_confirming_a_match_marks_the_booking_paid_with_the_logged_source_and_posts_income_once(): void
    {
        $tx = $this->line('FASTER PAYMENT '.$this->registration->payment_reference, 52.00);

        $this->actingAs($this->treasurer);
        $this->assertTrue($this->matcher()->reconcileTransaction($tx, 'event_registration', $this->registration->id));

        $registration = $this->registration->fresh();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('52.00', $registration->amount_paid);
        $this->assertSame(BankTransactionStatus::Matched, $tx->fresh()->status);

        $log = EventPaymentLog::sole();
        $this->assertSame('bank_reconciliation', $log->source);
        $this->assertSame($this->treasurer->id, $log->user_id, 'the treasurer who confirmed it is on record');
        $this->assertStringContainsString('Matched to bank line', $log->comment);
        $this->assertSame('2026-10-12', $log->received_at->toDateString());

        $this->assertSame(1, JournalEntry::where('club_id', $this->club->id)->count(), 'the payment is posted once, not twice');
        $credit = fn (string $code) => (float) JournalItem::where('account_id', Account::where('club_id', $this->club->id)->where('code', $code)->value('id'))->sum('credit');
        $this->assertEqualsWithDelta(50.0, $credit('4100'), 0.001);
        $this->assertEqualsWithDelta(2.0, $credit('4110'), 0.001);
    }

    public function test_another_clubs_booking_cannot_be_matched(): void
    {
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $tx = BankTransaction::create(['club_id' => $other->id, 'bank_import_id' => BankImport::create(['club_id' => $other->id, 'filename' => 'o.csv', 'total_lines' => 1, 'total_amount' => 52])->id, 'transaction_date' => '2026-10-12', 'raw_description' => 'x', 'amount' => 52.00, 'transaction_hash' => 'other', 'status' => BankTransactionStatus::Unmatched]);

        $this->assertNotContains('event_registration', array_column($this->matcher()->suggestMatches($tx), 'match_type'));
        $this->assertFalse($this->matcher()->reconcileTransaction($tx, 'event_registration', $this->registration->id));
        $this->assertSame('unpaid', $this->registration->fresh()->payment_status);
    }
}
