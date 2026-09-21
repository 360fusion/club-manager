<?php

namespace Tests\Feature;

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
use LogicException;
use Tests\TestCase;

class EventPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    private User $admin;

    private User $treasurer;

    private EventRegistration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->admin = $this->join('admin');
        $this->treasurer = $this->join('treasurer');
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(20), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 50, 'booking_fee_type' => 'fixed', 'booking_fee_amount' => 2]);
        $method = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'bank_transfer', 'label' => 'Bank transfer']);
        $option = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $method->id, 'is_enabled' => true]);

        $member = $this->join('member');
        $this->registration = app(EventRegistrationService::class)->register($this->event, $member, ['payment_method' => $option->id, 'attendees' => [['name' => 'Mia'], ['name' => 'Gary', 'is_guest' => true]]]);
    }

    private function join(string $role): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function url(string $action, ?EventRegistration $registration = null, string $slug = 'club-a'): string
    {
        return route("admin.events.payment.{$action}", ['clubSlug' => $slug, 'id' => $this->event->id, 'registrationId' => ($registration ?? $this->registration)->id]);
    }

    private function balance(string $code): float
    {
        $account = Account::where('club_id', $this->club->id)->where('code', $code)->first();

        return $account ? (float) JournalItem::where('account_id', $account->id)->sum('credit') - (float) JournalItem::where('account_id', $account->id)->sum('debit') : 0.0;
    }

    public function test_marking_paid_ticks_the_booking_logs_who_and_why_and_posts_income_and_fees(): void
    {
        $this->assertSame('102.00', $this->registration->total);

        $this->actingAs($this->admin)->post($this->url('paid'), ['method' => 'Bank transfer', 'received_on' => '2026-10-12', 'comment' => 'Checked statement, ref J SMITH'])->assertSessionHasNoErrors();

        $registration = $this->registration->fresh();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('102.00', $registration->amount_paid);
        $this->assertNotNull($registration->paid_at);

        $log = EventPaymentLog::sole();
        $this->assertSame('marked_paid', $log->action);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Checked statement, ref J SMITH', $log->comment);
        $this->assertSame('102.00', $log->amount);
        $this->assertSame('2026-10-12', $log->received_at->toDateString());

        $entry = JournalEntry::with('items')->where('source_type', 'EventRegistration')->sole();
        $this->assertEqualsWithDelta(102.0, $entry->items->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(100.0, $this->balance('4100'), 0.001, 'ticket income');
        $this->assertEqualsWithDelta(2.0, $this->balance('4110'), 0.001, 'booking fee income');
        $this->assertEqualsWithDelta(-102.0, $this->balance('1000'), 0.001, 'money in the bank');
    }

    public function test_part_payments_add_up_and_more_than_is_owed_is_refused(): void
    {
        $this->actingAs($this->admin)->post($this->url('paid'), ['amount' => 40])->assertSessionHasNoErrors();
        $this->assertSame('part_paid', $this->registration->fresh()->payment_status);
        $this->assertSame('marked_part_paid', EventPaymentLog::first()->action);

        $this->actingAs($this->admin)->post($this->url('paid'), ['amount' => 100])->assertSessionHasErrors('amount');
        $this->actingAs($this->admin)->post($this->url('paid'), ['amount' => 62])->assertSessionHasNoErrors();

        $this->assertSame('paid', $this->registration->fresh()->payment_status);
        $this->assertSame(2, EventPaymentLog::count());
        $this->actingAs($this->admin)->post($this->url('paid'))->assertSessionHasErrors('payment');
    }

    public function test_taking_a_payment_back_needs_a_reason_and_reverses_the_books(): void
    {
        $this->actingAs($this->admin)->post($this->url('paid'));

        $this->actingAs($this->treasurer)->post($this->url('unpaid'), ['comment' => ''])->assertSessionHasErrors('comment');
        $this->assertSame('paid', $this->registration->fresh()->payment_status);

        $this->actingAs($this->treasurer)->post($this->url('unpaid'), ['comment' => 'Cheque bounced'])->assertSessionHasNoErrors();

        $registration = $this->registration->fresh();
        $this->assertSame('unpaid', $registration->payment_status);
        $this->assertSame('0.00', $registration->amount_paid);
        $this->assertNull($registration->paid_at);
        $this->assertEqualsWithDelta(0.0, $this->balance('4100'), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->balance('1000'), 0.001);

        $history = EventPaymentLog::orderBy('id')->get();
        $this->assertSame(['marked_paid', 'marked_unpaid'], $history->pluck('action')->all());
        $this->assertSame([$this->admin->id, $this->treasurer->id], $history->pluck('user_id')->all());
        $this->assertSame('Cheque bounced', $history[1]->comment);
    }

    public function test_the_payment_history_can_never_be_edited_or_deleted(): void
    {
        $this->actingAs($this->admin)->post($this->url('paid'));
        $row = EventPaymentLog::sole();

        try {
            $row->update(['comment' => 'tampered']);
            $this->fail('History must not be editable.');
        } catch (LogicException) {
            $this->assertNull($row->fresh()->comment);
        }

        $this->expectException(LogicException::class);
        $row->delete();
    }

    public function test_a_charge_can_be_waived_only_if_nothing_was_paid(): void
    {
        $this->actingAs($this->admin)->post($this->url('waive'), ['comment' => ''])->assertSessionHasErrors('comment');
        $this->actingAs($this->admin)->post($this->url('waive'), ['comment' => 'Guest of honour'])->assertSessionHasNoErrors();
        $this->assertSame('waived', $this->registration->fresh()->payment_status);
        $this->actingAs($this->admin)->post($this->url('paid'))->assertSessionHasErrors('payment');

        $other = app(EventRegistrationService::class)->register($this->event, $this->join('member'), ['payment_method' => EventPaymentMethod::sole()->id, 'attendees' => [['name' => 'Other']]]);
        $this->actingAs($this->admin)->post($this->url('paid', $other));
        $this->actingAs($this->admin)->post($this->url('waive', $other), ['comment' => 'No thanks'])->assertSessionHasErrors('payment');
    }

    public function test_refunds_need_billing_permission_and_a_reason_and_reverse_the_income(): void
    {
        $coach = $this->join('coach');
        $this->actingAs($this->admin)->post($this->url('paid'));

        $this->actingAs($coach)->post($this->url('refund'), ['comment' => 'Cancelled'])->assertForbidden();
        $this->actingAs($this->treasurer)->post($this->url('refund'), ['comment' => ''])->assertSessionHasErrors('comment');
        $this->actingAs($this->treasurer)->post($this->url('refund'), ['comment' => 'Cancelled, full refund by bank'])->assertSessionHasNoErrors();

        $registration = $this->registration->fresh();
        $this->assertSame('refunded', $registration->payment_status);
        $this->assertSame('102.00', $registration->amount_refunded);
        $this->assertEqualsWithDelta(0.0, $this->balance('4100'), 0.001);
        $this->assertSame('refunded', EventPaymentLog::orderByDesc('id')->first()->action);
    }

    public function test_the_history_panel_shows_who_did_what_and_is_private_to_the_clubs_organisers(): void
    {
        $this->actingAs($this->admin)->post($this->url('paid'), ['comment' => 'Statement checked']);

        $this->actingAs($this->treasurer)->getJson($this->url('history'))->assertOk()
            ->assertJsonPath('history.0.by', $this->admin->name)
            ->assertJsonPath('history.0.comment', 'Statement checked')
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('can_refund', true);

        $this->actingAs($this->admin)->getJson($this->url('history'))->assertJsonPath('can_refund', true);

        $member = $this->join('member');
        $coach = $this->join('coach');
        $this->actingAs($coach)->getJson($this->url('history'))->assertForbidden();
        $this->actingAs($coach)->postJson($this->url('paid'))->assertForbidden();
        $otherClub = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $otherAdmin = User::factory()->create();
        $otherClub->users()->attach($otherAdmin->id, ['role' => 'admin', 'status' => 'active']);

        $this->actingAs($member)->getJson($this->url('history'))->assertForbidden();
        $this->actingAs($otherAdmin)->getJson($this->url('history'))->assertForbidden();
        $this->actingAs($otherAdmin)->postJson($this->url('paid', null, 'club-b'))->assertNotFound();
        $this->actingAs($member)->postJson($this->url('paid'))->assertForbidden();
    }

    public function test_several_bookings_can_be_marked_paid_together_and_cash_goes_to_petty_cash(): void
    {
        $service = app(EventRegistrationService::class);
        $option = EventPaymentMethod::sole();
        $second = $service->register($this->event, $this->join('member'), ['payment_method' => $option->id, 'attendees' => [['name' => 'Second']]]);
        $this->actingAs($this->admin)->post($this->url('paid', $second));

        $this->actingAs($this->admin)->post(route('admin.events.payment.bulk_paid', ['clubSlug' => 'club-a', 'id' => $this->event->id]), ['registration_ids' => [$this->registration->id, $second->id], 'comment' => 'April statement'])
            ->assertSessionHas('success', '1 booking marked as paid.');

        $this->assertSame('paid', $this->registration->fresh()->payment_status);
        $this->assertSame(2, EventPaymentLog::where('comment', null)->count() + EventPaymentLog::where('comment', 'April statement')->count());

        $door = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'cash_on_door', 'label' => 'On the night']);
        $doorOption = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $door->id, 'is_enabled' => true]);
        $cash = $service->register($this->event, $this->join('member'), ['payment_method' => $doorOption->id, 'attendees' => [['name' => 'Cash']]]);
        $this->actingAs($this->admin)->post($this->url('paid', $cash));

        $this->assertEqualsWithDelta(-52.0, $this->balance('1100'), 0.001, 'cash taken on the night is held as petty cash');
    }

    public function test_only_billing_roles_get_the_payment_controls_on_the_registrations_page(): void
    {
        $coach = $this->join('coach');
        $url = route('admin.events.subscribers', ['clubSlug' => 'club-a', 'id' => $this->event->id]);

        foreach ([[$this->admin, true], [$this->treasurer, true], [$coach, false]] as [$user, $expected]) {
            $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn ($page) => $page
                ->where('canManagePayments', $expected)
                ->where('subscribers.0.total', '102.00')
                ->where('subscribers.0.balance', '102.00')
                ->where('subscribers.0.payment_status', 'unpaid'));
        }
    }
}
