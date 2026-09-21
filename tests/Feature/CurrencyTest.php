<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\GrandLodge;
use App\Models\Invoice;
use App\Models\Province;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private ClubType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
    }

    private function club(string $slug, ?string $country = null, array $settings = []): Club
    {
        $provinceId = null;

        if ($country) {
            $grandLodge = GrandLodge::create(['name' => "GL {$country}", 'code' => "gl-{$slug}", 'short_name' => "GL {$slug}", 'country' => $country]);
            $provinceId = Province::create(['grand_lodge_id' => $grandLodge->id, 'name' => "Province {$slug}", 'code' => "p-{$slug}", 'country' => $country])->id;
        }

        return Club::create(['club_type_id' => $this->type->id, 'name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'province_id' => $provinceId, 'settings' => $settings ?: null]);
    }

    private function admin(Club $club, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    public function test_only_currencies_where_lodges_exist_are_offered(): void
    {
        $this->assertSame(['GBP'], array_keys(Currencies::available()));

        GrandLodge::create(['name' => 'Grand Lodge of Ireland', 'code' => 'gli', 'short_name' => 'GLI', 'country' => 'Ireland']);
        GrandLodge::create(['name' => 'Grand Lodge of Australia', 'code' => 'gla', 'short_name' => 'GLA', 'country' => 'Australia']);
        GrandLodge::create(['name' => 'Grand Lodge of Nowhere', 'code' => 'gln', 'short_name' => 'GLN', 'country' => 'Atlantis']);

        $available = array_keys(Currencies::available());

        $this->assertSame(['GBP', 'EUR', 'AUD'], $available);
        $this->assertNotContains('USD', $available);
        $this->assertFalse(Currencies::isKnown('JPY'));
    }

    public function test_a_clubs_currency_comes_from_its_choice_else_its_country_else_the_default(): void
    {
        $this->assertSame('EUR', $this->club('dublin', 'Ireland')->currencyCode());
        $this->assertSame('€', $this->club('dublin2', 'Ireland')->currencySymbol());
        $this->assertSame('GBP', $this->club('nowhere')->currencyCode());
        $this->assertSame('CAD', $this->club('toronto', 'Canada', ['currency' => 'cad'])->currencyCode());
        $this->assertSame('AUD', $this->club('sydney', 'Australia', ['currency' => 'JPY'])->currencyCode(), 'an unknown stored value falls back to the country');
    }

    public function test_amounts_are_formatted_in_the_clubs_own_currency(): void
    {
        $zurich = $this->club('zurich', 'Switzerland');

        $this->assertSame('CHF 1,250.50', Currencies::format(1250.5, $zurich));
        $this->assertSame('-CHF 3.00', Currencies::format(-3, $zurich));
        $this->assertSame('£10.00', Currencies::format(10, null));
    }

    public function test_a_club_admin_can_pick_an_available_currency_but_not_an_unused_one(): void
    {
        $club = $this->club('dublin', 'Ireland');
        $admin = $this->admin($club);
        $url = route('admin.settings.update', ['clubSlug' => 'dublin']);

        $this->actingAs($admin)->put($url, ['currency' => 'USD'])->assertSessionHasErrors('currency');
        $this->actingAs($admin)->put($url, ['currency' => 'JPY'])->assertSessionHasErrors('currency');
        $this->actingAs($admin)->put($url, ['currency' => 'GBP'])->assertSessionHasNoErrors();

        $this->assertSame('GBP', $club->fresh()->currencyCode());
    }

    public function test_the_currency_is_locked_once_the_club_has_financial_records(): void
    {
        $club = $this->club('dublin', 'Ireland');
        $admin = $this->admin($club);
        $url = route('admin.settings.update', ['clubSlug' => 'dublin']);

        $this->assertFalse($club->currencyIsLocked());
        Invoice::create(['club_id' => $club->id, 'user_id' => $admin->id, 'invoice_number' => 'INV-1', 'title' => 'Dues', 'amount' => 50, 'status' => 'unpaid']);

        $this->assertTrue($club->fresh()->currencyIsLocked());
        $this->actingAs($admin)->put($url, ['currency' => 'GBP'])->assertSessionHasErrors('currency');
        $this->assertSame('EUR', $club->fresh()->currencyCode());

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($superAdmin)->put($url, ['currency' => 'GBP'])->assertSessionHasNoErrors();
        $this->assertSame('GBP', $club->fresh()->currencyCode());
    }

    public function test_every_page_gets_the_currency_of_the_club_in_the_url(): void
    {
        $club = $this->club('dublin', 'Ireland');
        $admin = $this->admin($club);

        $this->actingAs($admin)->get(route('admin.settings.show', ['clubSlug' => 'dublin']))
            ->assertInertia(fn ($page) => $page->where('currency.code', 'EUR')->where('currency.symbol', '€')->where('currencyLocked', false)->has('currencies', 2));
    }

    public function test_bank_accounts_and_ledger_accounts_use_the_clubs_currency(): void
    {
        $club = $this->club('dublin', 'Ireland');
        $admin = $this->admin($club);

        Livewire::actingAs($admin)->test(BankAccountsIndex::class, ['clubSlug' => 'dublin'])
            ->assertSee('€')
            ->set('bank_name', 'AIB')
            ->set('account_name', 'Lodge account')
            ->set('account_type', 'current')
            ->set('currency', 'USD')
            ->call('saveBankAccount');

        $this->assertSame(['EUR'], BankAccount::where('club_id', $club->id)->pluck('currency')->unique()->values()->all());
    }

    public function test_the_migration_keeps_existing_clubs_with_records_on_pounds(): void
    {
        $withRecords = $this->club('cork', 'Ireland');
        $fresh = $this->club('galway', 'Ireland');
        $chosen = $this->club('perth', 'Australia', ['currency' => 'AUD']);
        Invoice::create(['club_id' => $withRecords->id, 'user_id' => $this->admin($withRecords)->id, 'invoice_number' => 'INV-1', 'title' => 'Dues', 'amount' => 50, 'status' => 'unpaid']);
        Invoice::create(['club_id' => $chosen->id, 'user_id' => $this->admin($chosen)->id, 'invoice_number' => 'INV-2', 'title' => 'Dues', 'amount' => 50, 'status' => 'unpaid']);

        (require database_path('migrations/2026_09_21_123400_pin_existing_club_currencies.php'))->up();

        $this->assertSame('GBP', $withRecords->fresh()->currencyCode());
        $this->assertSame('EUR', $fresh->fresh()->currencyCode());
        $this->assertSame('AUD', $chosen->fresh()->currencyCode());
    }
}
