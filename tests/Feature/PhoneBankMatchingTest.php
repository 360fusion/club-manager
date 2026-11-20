<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneBankMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    private MemberSubscription $dues;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->treasurer = User::factory()->create();
        $this->club->users()->attach($this->treasurer->id, ['role' => 'treasurer', 'status' => 'active']);
        $member = Member::create(['club_id' => $this->club->id, 'first_name' => 'Thomas', 'last_name' => 'Hartley', 'email' => 'tom@example.test', 'membership_status' => 'active']);
        $this->dues = MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $member->id, 'billing_year' => 2027, 'due_date' => now()->addDays(10)->toDateString(), 'amount_due' => 180, 'amount_paid' => 0, 'status' => SubscriptionStatus::Unpaid, 'invoice_reference' => 'SUB-2027-014']);
    }

    private function line(Club $club, string $description, string $reference, float $amount): BankTransaction
    {
        $import = BankImport::create(['club_id' => $club->id, 'filename' => 's.csv', 'total_lines' => 1, 'total_amount' => $amount]);

        return BankTransaction::create(['club_id' => $club->id, 'bank_import_id' => $import->id, 'transaction_date' => now()->toDateString(), 'raw_description' => $description, 'reference' => $reference, 'amount' => $amount, 'transaction_hash' => md5($description.$reference.$amount), 'status' => BankTransactionStatus::Unmatched]);
    }

    public function test_the_treasurer_sees_certain_matches_and_confirms_one_on_the_phone(): void
    {
        $line = $this->line($this->club, 'BACS T HARTLEY', 'SUB-2027-014', 180);
        $this->line($this->club, 'TESCO STORES', 'DEBIT', -18.40);

        $this->actingAs($this->treasurer)->get(route('member.accounts.match', $this->club->slug))
            ->assertInertia(fn ($page) => $page->component('Member/Accounts/Match')
                ->has('lines', 2)
                ->where('lines', fn ($lines) => collect($lines)->firstWhere('id', $line->id)['match'] !== null
                    && collect($lines)->firstWhere('description', 'TESCO STORES')['match'] === null));

        $this->actingAs($this->treasurer)->post(route('member.accounts.match.confirm', [$this->club->slug, $line->id]))->assertSessionHasNoErrors();

        $this->assertSame(BankTransactionStatus::Matched, $line->fresh()->status);
        $this->assertSame(SubscriptionStatus::Paid, $this->dues->fresh()->status);
    }

    public function test_confirm_all_takes_only_the_certain_matches(): void
    {
        $line = $this->line($this->club, 'BACS T HARTLEY', 'SUB-2027-014', 180);
        $unsure = $this->line($this->club, 'BACS T HARTLEY', 'DUES', 50);

        $this->actingAs($this->treasurer)->post(route('member.accounts.match.all', $this->club->slug))->assertSessionHas('success', '1 line matched by reference and amount.');

        $this->assertSame(BankTransactionStatus::Matched, $line->fresh()->status);
        $this->assertSame(BankTransactionStatus::Unmatched, $unsure->fresh()->status);
    }

    public function test_a_plain_member_cannot_match_and_another_lodges_line_is_not_found(): void
    {
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $line = $this->line($this->club, 'BACS T HARTLEY', 'SUB-2027-014', 180);
        $elsewhere = $this->line(Club::factory()->create(), 'BACS T HARTLEY', 'SUB-2027-014', 180);

        $this->actingAs($member)->get(route('member.accounts.match', $this->club->slug))->assertForbidden();
        $this->actingAs($member)->post(route('member.accounts.match.confirm', [$this->club->slug, $line->id]))->assertForbidden();
        $this->actingAs($this->treasurer)->post(route('member.accounts.match.confirm', [$this->club->slug, $elsewhere->id]))->assertNotFound();
        $this->assertSame(BankTransactionStatus::Unmatched, $line->fresh()->status);
    }
}
