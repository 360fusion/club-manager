<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Services\ReliefChestExportService;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharityRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->treasurer = $this->makeClubAdmin(User::factory()->create(), $this->club);
    }

    public function test_gift_aid_is_only_counted_on_a_gift_from_a_named_donor(): void
    {
        $collect = fn (array $extra) => $this->actingAs($this->treasurer)->post(route('admin.charity.collections.store', $this->club->slug), $extra + ['collection_type' => 'alms_plate', 'cash_amount' => 40, 'cheque_amount' => 0])->assertSessionHasNoErrors();

        $collect([]);
        $collect(['donor_name' => 'W Bro J Smith']);

        [$anonymous, $named] = CharityCollection::where('club_id', $this->club->id)->orderBy('id')->get()->all();
        $this->assertFalse($anonymous->is_gift_aid_eligible);
        $this->assertEquals(0, $anonymous->gift_aid_amount);
        $this->assertTrue($named->is_gift_aid_eligible);
        $this->assertEquals(10, $named->gift_aid_amount);
    }

    public function test_the_payment_schedule_lists_only_grants_the_lodge_has_voted_for(): void
    {
        foreach ([GrantApprovalStatus::Proposed, GrantApprovalStatus::CommitteeApproved, GrantApprovalStatus::LodgeVoted] as $status) {
            CharityGrant::create(['club_id' => $this->club->id, 'recipient_name' => $status->value.' recipient', 'purpose' => 'Relief', 'amount' => 100, 'approval_status' => $status]);
        }

        $csv = app(ReliefChestExportService::class)->generateBacsSchedule($this->club);

        $this->assertStringContainsString('lodge_voted recipient', $csv);
        $this->assertStringNotContainsString('proposed recipient', $csv);
        $this->assertStringNotContainsString('committee_approved recipient', $csv);
    }
}
