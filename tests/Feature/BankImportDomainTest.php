<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Livewire\Banking\BankImportIndex;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Services\BankStatementParserService;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BankImportDomainTest extends TestCase
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
    }

    public function test_can_parse_uk_csv_statement_with_paid_in_paid_out_columns(): void
    {
        $csvContent = "Date,Description,Reference,Paid In,Paid Out,Balance\n";
        $csvContent .= "15/09/2026,Annual Dues Bro Smith,INV-2026-M001,160.00,,1160.00\n";
        $csvContent .= "16/09/2026,Hall Catering Supplier,INV-BILL-99,,85.50,1074.50\n";

        $service = new BankStatementParserService;
        $bundle = $service->parseCsv($csvContent, 'barclays.csv', $this->club->id);

        $this->assertEquals(2, $bundle['total_lines']);
        $this->assertEquals(2, $bundle['new_lines_count']);
        $this->assertEquals(0, $bundle['duplicate_lines_count']);
        $this->assertEquals(74.50, $bundle['total_amount']); // 160.00 - 85.50

        $this->assertEquals(160.00, $bundle['lines'][0]['amount']);
        $this->assertEquals(-85.50, $bundle['lines'][1]['amount']);
    }

    public function test_rows_with_an_unreadable_date_or_amount_are_reported_not_dropped_or_made_zero(): void
    {
        $csv = "Date,Description,Reference,Paid In,Paid Out,Balance\n"
            ."05/10/2026,Good subs,A1,120.00,,\n"
            ."not-a-date,Bad date,A2,10.00,,\n"
            ."06/11/2026,Bad amount,A3,abc,,\n"
            ."07/11/2026,No amount,A4,,,\n"
            ."08/11/2026,Formatted,A5,\"£1,234.50\",,\n"
            ."09/11/2026,Small,A6,.50,,\n";

        $bundle = (new BankStatementParserService)->parseCsv($csv, 'mixed.csv', $this->club->id);

        $this->assertSame(['Good subs', 'Formatted', 'Small'], array_column($bundle['lines'], 'raw_description'));
        $this->assertSame([1234.50, 0.50], [$bundle['lines'][1]['amount'], $bundle['lines'][2]['amount']]);
        $this->assertSame(
            [[3, 'The date could not be read.'], [4, 'The amount could not be read.'], [5, 'The line has no amount.']],
            array_map(fn ($row) => [$row['line'], $row['reason']], $bundle['skipped_rows']),
        );
    }

    public function test_ofx_transactions_with_an_unreadable_date_or_amount_are_reported(): void
    {
        $ofx = "<OFX><BANKTRANLIST>\n"
            ."<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20261102<TRNAMT>60.00<FITID>1<NAME>Good</STMTTRN>\n"
            ."<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>soon<TRNAMT>5.00<FITID>2<NAME>Bad date</STMTTRN>\n"
            ."<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20261103<TRNAMT>lots<FITID>3<NAME>Bad amount</STMTTRN>\n"
            .'</BANKTRANLIST></OFX>';

        $bundle = (new BankStatementParserService)->parseOfx($ofx, 'mixed.ofx', $this->club->id);

        $this->assertCount(1, $bundle['lines']);
        $this->assertSame(['The date could not be read.', 'The amount could not be read.'], array_column($bundle['skipped_rows'], 'reason'));
    }

    public function test_files_that_are_not_statements_are_refused_with_a_message(): void
    {
        $this->actingAs($this->adminUser);
        $upload = fn (string $name, string $content) => Livewire::test(BankImportIndex::class, ['clubSlug' => $this->club->slug])
            ->set('statementFile', UploadedFile::fake()->createWithContent($name, $content));

        $upload('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')->assertHasErrors('statementFile')->assertSet('showPreviewModal', false)->assertSee('must be a CSV, TXT, or OFX');
        $upload('page.html', '<html></html>')->assertHasErrors('statementFile')->assertSet('showPreviewModal', false);
        $upload('notes.csv', 'hello world, this is not a statement')
            ->assertHasErrors('statementFile')
            ->assertSet('showPreviewModal', false)
            ->assertSee('No transactions were found');
    }

    public function test_the_import_preview_warns_about_rows_that_will_not_be_imported(): void
    {
        $this->actingAs($this->adminUser);
        $file = UploadedFile::fake()->createWithContent('statement.csv', "Date,Description,Amount\n2026-09-15,Subs,160.00\nnot-a-date,Oops,5.00\n");

        Livewire::test(BankImportIndex::class, ['clubSlug' => $this->club->slug])
            ->set('statementFile', $file)
            ->assertSee('could not be read and will')
            ->assertSee('The date could not be read.');
    }

    public function test_can_parse_ofx_statement_file(): void
    {
        $ofxContent = <<<'OFX'
<OFX>
<BANKMSGSRSV1>
<STMTTRNRS>
<STMTRS>
<BANKACCTFROM>
<BANKID>200000
<ACCTID>12345678
</BANKACCTFROM>
<BANKTRANLIST>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20260915120000
<TRNAMT>160.00
<FITID>REF20260915001
<NAME>Annual Dues Payment Bro Johnson
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20260916120000
<TRNAMT>-45.00
<FITID>REF20260916002
<NAME>Lodge Festive Board Wine
</STMTTRN>
</BANKTRANLIST>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $service = new BankStatementParserService;
        $bundle = $service->parseOfx($ofxContent, 'natwest.ofx', $this->club->id);

        $this->assertEquals('12345678', $bundle['account_number']);
        $this->assertEquals('200000', $bundle['sort_code']);
        $this->assertEquals(2, $bundle['total_lines']);
        $this->assertEquals(160.00, $bundle['lines'][0]['amount']);
        $this->assertEquals(-45.00, $bundle['lines'][1]['amount']);
    }

    public function test_deduplication_hash_skips_previously_imported_transactions(): void
    {
        $service = new BankStatementParserService;

        // 1. First import
        $csv1 = "Date,Description,Amount,Balance\n";
        $csv1 .= "2026-09-15,Direct Dues Bro Taylor,160.00,2000.00\n";

        $bundle1 = $service->parseCsv($csv1, 'import1.csv', $this->club->id);
        $service->importParsedBundle($this->club, $bundle1);

        $this->assertDatabaseHas('club_acc_bank_transactions', [
            'raw_description' => 'Direct Dues Bro Taylor',
            'amount' => 160.00,
        ]);

        // 2. Second import containing same line + 1 new line
        $csv2 = "Date,Description,Amount,Balance\n";
        $csv2 .= "2026-09-15,Direct Dues Bro Taylor,160.00,2000.00\n";
        $csv2 .= "2026-09-16,Grand Lodge Annual Returns,-120.00,1880.00\n";

        $bundle2 = $service->parseCsv($csv2, 'import2.csv', $this->club->id);

        $this->assertEquals(2, $bundle2['total_lines']);
        $this->assertEquals(1, $bundle2['new_lines_count']);
        $this->assertEquals(1, $bundle2['duplicate_lines_count']);
        $this->assertTrue($bundle2['lines'][0]['is_duplicate']);
        $this->assertFalse($bundle2['lines'][1]['is_duplicate']);

        $importBatch2 = $service->importParsedBundle($this->club, $bundle2);
        $this->assertEquals(1, $importBatch2->total_lines);
    }

    public function test_bank_import_livewire_component_upload(): void
    {
        Storage::fake('tmp-for-tests');
        $this->actingAs($this->adminUser);

        $file = UploadedFile::fake()->createWithContent('statement.csv', "Date,Description,Amount\n2026-09-15,Lodge Subscriptions,160.00\n");

        Livewire::test(BankImportIndex::class, ['clubSlug' => $this->club->slug])
            ->assertStatus(200)
            ->set('statementFile', $file)
            ->assertSet('showPreviewModal', true)
            ->call('confirmImport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_bank_imports', [
            'filename' => 'statement.csv',
            'total_lines' => 1,
        ]);

        $this->assertDatabaseHas('club_acc_bank_transactions', [
            'raw_description' => 'Lodge Subscriptions',
            'amount' => 160.00,
            'status' => BankTransactionStatus::Unmatched->value,
        ]);
    }

    private function bankAccount(string $name): BankAccount
    {
        return BankAccount::create(['club_id' => $this->club->id, 'bank_name' => 'Lloyds', 'account_name' => $name, 'account_type' => 'current', 'is_active' => true]);
    }

    public function test_a_statement_is_filed_under_the_only_bank_account_without_asking(): void
    {
        $only = $this->bankAccount('Current');
        $this->actingAs($this->adminUser);
        $file = UploadedFile::fake()->createWithContent('statement.csv', "Date,Description,Amount\n2026-09-15,Lodge Subscriptions,160.00\n");

        Livewire::test(BankImportIndex::class, ['clubSlug' => $this->club->slug])
            ->set('statementFile', $file)
            ->assertSet('bankAccountId', $only->id)
            ->call('confirmImport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_bank_transactions', ['club_id' => $this->club->id, 'bank_account_id' => $only->id]);
        $this->assertDatabaseHas('club_acc_bank_imports', ['club_id' => $this->club->id, 'bank_account_id' => $only->id]);
    }

    public function test_with_several_bank_accounts_the_import_must_say_which_one(): void
    {
        $this->bankAccount('Current');
        $savings = $this->bankAccount('Savings');
        $other = BankAccount::create(['club_id' => Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true])->id, 'bank_name' => 'X', 'account_name' => 'Y', 'account_type' => 'current', 'is_active' => true]);
        $this->actingAs($this->adminUser);
        $file = UploadedFile::fake()->createWithContent('statement.csv', "Date,Description,Amount\n2026-09-15,Lodge Subscriptions,160.00\n");

        $component = Livewire::test(BankImportIndex::class, ['clubSlug' => $this->club->slug])
            ->set('statementFile', $file)
            ->assertSet('bankAccountId', null)
            ->call('confirmImport')
            ->assertHasErrors('bankAccountId');
        $this->assertDatabaseCount('club_acc_bank_transactions', 0);

        $component->set('bankAccountId', $other->id)->call('confirmImport')->assertHasErrors('bankAccountId');
        $this->assertDatabaseCount('club_acc_bank_transactions', 0);

        $component->set('bankAccountId', $savings->id)->call('confirmImport')->assertHasNoErrors();
        $this->assertDatabaseHas('club_acc_bank_transactions', ['club_id' => $this->club->id, 'bank_account_id' => $savings->id]);
    }
}
