<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\DirectDebitMandate;
use App\Domains\ClubAccounting\Services\GoCardlessSyncService;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoCardlessIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function createTestClub(): Club
    {
        $clubType = ClubType::firstOrCreate(
            ['code' => 'lodge'],
            ['name' => 'Masonic Lodge', 'available_modules' => ['accounting']]
        );

        return Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity-'.uniqid(),
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);
    }

    public function test_can_connect_gocardless_bank_account(): void
    {
        $user = User::factory()->create();
        $club = $this->createTestClub();
        $user->clubs()->attach($club->id, ['role' => 'admin']);

        Http::fake([
            'https://api-sandbox.gocardless.com/creditors' => Http::response([
                'creditors' => [
                    ['name' => 'Test Club Sandbox'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->post(route('admin.accounting.bank_accounts.gocardless.connect', $club->slug), [
                'account_name' => 'Lodge Direct Debit Account',
                'gocardless_access_token' => 'sandbox_test_token_123',
                'gocardless_environment' => 'sandbox',
                'gocardless_webhook_secret' => 'whsec_test_123',
                'currency' => 'GBP',
                'opening_balance' => 100.00,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('club_acc_bank_accounts', [
            'club_id' => $club->id,
            'bank_name' => 'GoCardless',
            'account_name' => 'Lodge Direct Debit Account',
            'gocardless_environment' => 'sandbox',
            'sync_status' => 'connected',
        ]);
    }

    public function test_gocardless_sync_service_test_connection(): void
    {
        Http::fake([
            'https://api-sandbox.gocardless.com/creditors' => Http::response([
                'creditors' => [
                    ['name' => 'Test Club Sandbox'],
                ],
            ], 200),
        ]);

        $syncService = new GoCardlessSyncService;
        $result = $syncService->testConnection('sandbox_token_123', 'sandbox');

        $this::assertTrue($result['success']);
        $this::assertStringContainsString('Test Club Sandbox', $result['message']);
    }

    public function test_direct_debit_mandate_relationship(): void
    {
        $user = User::factory()->create();
        $club = $this->createTestClub();

        $mandate = DirectDebitMandate::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'gocardless_customer_id' => 'CU12345',
            'gocardless_mandate_id' => 'MD12345',
            'scheme' => 'bacs',
            'status' => 'active',
            'bank_name' => 'Barclays',
            'account_holder_name' => $user->name,
            'account_number_ending' => '99',
        ]);

        $this::assertEquals($club->id, $mandate->club->id);
        $this::assertEquals($user->id, $mandate->user->id);
        $this::assertEquals('active', $mandate->status);
    }
}
