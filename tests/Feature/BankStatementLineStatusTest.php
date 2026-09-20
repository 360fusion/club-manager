<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankStatementLineStatusTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private int $transactionId;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'code' => 'craft_lodge',
            'name' => 'Craft Lodge',
            'available_modules' => [],
        ]);

        $this->club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Test Lodge',
            'slug' => 'test-lodge',
        ]);

        $importId = DB::table('club_acc_bank_imports')->insertGetId([
            'club_id' => $this->club->id,
            'filename' => 'statement.csv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->transactionId = DB::table('club_acc_bank_transactions')->insertGetId([
            'club_id' => $this->club->id,
            'bank_import_id' => $importId,
            'transaction_date' => now()->toDateString(),
            'raw_description' => 'Test payment',
            'amount' => 25.00,
            'transaction_hash' => 'hash-'.uniqid(),
            'status' => BankTransactionStatus::Unmatched->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleting_a_statement_line_marks_it_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(
            "/clubs/{$this->club->slug}/admin/accounting/statement-lines/delete",
            ['transaction_ids' => [$this->transactionId]],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('club_acc_bank_transactions', [
            'id' => $this->transactionId,
            'status' => BankTransactionStatus::Ignored->value,
        ]);
    }

    public function test_restoring_a_statement_line_marks_it_unmatched(): void
    {
        $user = User::factory()->create();

        DB::table('club_acc_bank_transactions')
            ->where('id', $this->transactionId)
            ->update(['status' => BankTransactionStatus::Ignored->value]);

        $response = $this->actingAs($user)->post(
            "/clubs/{$this->club->slug}/admin/accounting/statement-lines/restore",
            ['transaction_ids' => [$this->transactionId]],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('club_acc_bank_transactions', [
            'id' => $this->transactionId,
            'status' => BankTransactionStatus::Unmatched->value,
        ]);
    }
}
