<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Models\Accounting\Account;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiBankAccountDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings', 'members', 'banking'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->clubs()->attach($this->club->id, ['role' => 'admin']);

        // Seed Chart of Accounts
        $accService = new AccountingService;
        $accService->seedDefaultAccounts($this->club);
    }

    public function test_auto_provisions_default_operating_bank_account_on_mount(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(BankAccountsIndex::class, ['clubSlug' => $this->club->slug])
            ->assertStatus(200);

        $this->assertDatabaseHas('club_acc_bank_accounts', [
            'club_id' => $this->club->id,
            'bank_name' => 'High Street Bank',
            'account_name' => 'Main Operating Account',
            'account_type' => 'current',
        ]);
    }

    public function test_can_create_new_stripe_payment_gateway_account_and_link_nominal_code(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(BankAccountsIndex::class, ['clubSlug' => $this->club->slug])
            ->set('bank_name', 'Stripe')
            ->set('account_name', 'Online Card Payouts')
            ->set('account_type', 'payment_gateway')
            ->set('currency', 'GBP')
            ->set('opening_balance', 150.00)
            ->call('saveBankAccount')
            ->assertHasNoErrors();

        // Verify BankAccount record was saved
        $bankAcc = BankAccount::where('club_id', $this->club->id)
            ->where('bank_name', 'Stripe')
            ->first();

        $this->assertNotNull($bankAcc);
        $this->assertEquals('Online Card Payouts', $bankAcc->account_name);
        $this->assertEquals('payment_gateway', $bankAcc->account_type);

        // Verify matching Chart of Accounts Asset entry was generated (e.g. Code 1010)
        $this->assertNotNull($bankAcc->account_id);
        $ledgerAcc = Account::find($bankAcc->account_id);
        $this->assertEquals('1010', $ledgerAcc->code);
        $this->assertEquals('Stripe — Online Card Payouts', $ledgerAcc->name);
    }

    public function test_can_toggle_bank_account_active_status(): void
    {
        $this->actingAs($this->adminUser);

        $bankAcc = BankAccount::create([
            'club_id' => $this->club->id,
            'bank_name' => 'SumUp',
            'account_name' => 'Meeting Card Reader',
            'account_type' => 'merchant',
            'is_active' => true,
        ]);

        Livewire::test(BankAccountsIndex::class, ['clubSlug' => $this->club->slug])
            ->call('toggleAccountActive', $bankAcc->id);

        $this->assertFalse($bankAcc->fresh()->is_active);
    }
}
