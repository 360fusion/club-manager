<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $clubType = ClubType::create([
            'name' => 'Boating Club',
            'code' => 'boating',
            'available_modules' => ['accounting', 'posts'],
        ]);

        $this->club = Club::create([
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->clubs()->attach($this->club, ['role' => 'admin']);

        // Seed basic accounts
        Account::create(['club_id' => $this->club->id, 'code' => '1000', 'name' => 'Operating Bank Account', 'type' => 'asset', 'current_balance' => 0]);
        Account::create(['club_id' => $this->club->id, 'code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'current_balance' => 0]);
        Account::create(['club_id' => $this->club->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'current_balance' => 0]);
        Account::create(['club_id' => $this->club->id, 'code' => '4000', 'name' => 'Membership Dues Income', 'type' => 'revenue', 'current_balance' => 0]);
        Account::create(['club_id' => $this->club->id, 'code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense', 'current_balance' => 0]);
    }

    public function test_admin_can_record_vendor_bill_with_receipt_attachment(): void
    {
        $receiptFile = UploadedFile::fake()->image('fuel-receipt.jpg', 600, 800);

        $response = $this->actingAs($this->user)
            ->post("/{$this->club->slug}/admin/accounting/bills", [
                'vendor_name' => 'Oxford Marine Fuel Ltd',
                'category' => 'Boating Equipment & Supplies',
                'amount' => 185.50,
                'due_date' => now()->addDays(14)->format('Y-m-d'),
                'notes' => 'Diesel for coaching launch',
                'attachment' => $receiptFile,
            ]);

        $response->assertRedirect();

        $bill = Bill::where('club_id', $this->club->id)->first();
        $this->assertNotNull($bill);
        $this->assertEquals('Oxford Marine Fuel Ltd', $bill->vendor_name);
        $this->assertNotNull($bill->media_id);

        $media = $bill->media;
        $this->assertNotNull($media);
        $this->assertEquals('accounting', $media->collection_name);
        $this->assertEquals('fuel-receipt.jpg', $media->file_name);
        $this->assertTrue($media->getCustomProperty('is_accounting_protected'));
        $this->assertEquals('accounting', $media->getCustomProperty('source'));
    }

    public function test_admin_can_create_invoice_with_pdf_attachment(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club, ['role' => 'member']);

        $pdfFile = UploadedFile::fake()->create('membership-schedule.pdf', 150, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post("/{$this->club->slug}/admin/accounting/invoices", [
                'user_id' => $member->id,
                'title' => 'Annual Membership Dues 2026',
                'amount' => 120.00,
                'attachment' => $pdfFile,
            ]);

        $response->assertRedirect("/{$this->club->slug}/admin/accounting/sales");

        $invoice = Invoice::where('club_id', $this->club->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('Annual Membership Dues 2026', $invoice->title);
        $this->assertNotNull($invoice->media_id);

        $media = $invoice->media;
        $this->assertNotNull($media);
        $this->assertEquals('accounting', $media->collection_name);
        $this->assertEquals('membership-schedule.pdf', $media->file_name);
        $this->assertTrue($media->getCustomProperty('is_accounting_protected'));
    }

    public function test_media_manager_blocks_deleting_accounting_protected_files(): void
    {
        $receiptFile = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post("/{$this->club->slug}/admin/accounting/bills", [
                'vendor_name' => 'Riverside Rigging',
                'category' => 'Repairs',
                'amount' => 75.00,
                'due_date' => now()->format('Y-m-d'),
                'attachment' => $receiptFile,
            ]);

        $bill = Bill::where('club_id', $this->club->id)->firstOrFail();
        $mediaId = $bill->media_id;

        // Attempt soft delete via File Manager
        $deleteRes = $this->actingAs($this->user)
            ->deleteJson("/{$this->club->slug}/admin/media/{$mediaId}");

        $deleteRes->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'deleted_at' => null,
        ]);

        // Attempt permanent force delete via File Manager
        $forceRes = $this->actingAs($this->user)
            ->deleteJson("/{$this->club->slug}/admin/media/{$mediaId}/force");

        $forceRes->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
        ]);
    }

    public function test_media_manager_bulk_delete_skips_accounting_protected_files(): void
    {
        $receiptFile = UploadedFile::fake()->image('bill-receipt.png', 400, 400);

        $this->actingAs($this->user)
            ->post("/{$this->club->slug}/admin/accounting/bills", [
                'vendor_name' => 'Boat Repair Yard',
                'category' => 'Repairs',
                'amount' => 300.00,
                'due_date' => now()->format('Y-m-d'),
                'attachment' => $receiptFile,
            ]);

        $protectedMediaId = Bill::where('club_id', $this->club->id)->firstOrFail()->media_id;

        // Create a regular non-accounting file
        $regularUpload = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", [
                'file' => UploadedFile::fake()->image('regular-photo.jpg'),
                'folder' => 'images',
            ]);
        $regularMediaId = $regularUpload->json('media.id');

        // Bulk delete both
        $bulkRes = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/bulk-delete", [
                'ids' => [$protectedMediaId, $regularMediaId],
            ]);

        $bulkRes->assertOk()
            ->assertJsonPath('success', true);

        // Protected item is untouched
        $this->assertDatabaseHas('media', ['id' => $protectedMediaId, 'deleted_at' => null]);
        // Regular item is soft-deleted
        $this->assertSoftDeleted('media', ['id' => $regularMediaId]);
    }

    public function test_admin_can_delete_attachment_from_accounting_page(): void
    {
        $receiptFile = UploadedFile::fake()->image('receipt-to-delete.jpg');

        $this->actingAs($this->user)
            ->post("/{$this->club->slug}/admin/accounting/bills", [
                'vendor_name' => 'Hardware Store',
                'category' => 'Maintenance',
                'amount' => 45.00,
                'due_date' => now()->format('Y-m-d'),
                'attachment' => $receiptFile,
            ]);

        $bill = Bill::where('club_id', $this->club->id)->firstOrFail();
        $mediaId = $bill->media_id;
        $this->assertNotNull($mediaId);

        // Delete attachment from Accounting endpoint
        $delRes = $this->actingAs($this->user)
            ->delete("/{$this->club->slug}/admin/accounting/bills/{$bill->id}/attachment");

        $delRes->assertRedirect();

        $bill->refresh();
        $this->assertNull($bill->media_id);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }

    public function test_admin_can_delete_unpaid_bill_from_accounting_page(): void
    {
        $receiptFile = UploadedFile::fake()->image('paint-receipt.png');

        $this->actingAs($this->user)
            ->post("/{$this->club->slug}/admin/accounting/bills", [
                'vendor_name' => 'Marine Paints Co',
                'category' => 'Maintenance',
                'amount' => 110.00,
                'due_date' => now()->format('Y-m-d'),
                'attachment' => $receiptFile,
            ]);

        $bill = Bill::where('club_id', $this->club->id)->firstOrFail();
        $mediaId = $bill->media_id;

        // Delete bill from Accounting endpoint
        $delRes = $this->actingAs($this->user)
            ->delete("/{$this->club->slug}/admin/accounting/bills/{$bill->id}");

        $delRes->assertRedirect();

        $this->assertDatabaseMissing('accounting_bills', ['id' => $bill->id]);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }

    public function test_media_usage_reports_accounting_bill_attachment(): void
    {
        $receiptFile = UploadedFile::fake()->image('rigging-bill.png');

        $this->actingAs($this->user)
            ->post("/{$this->club->slug}/admin/accounting/bills", [
                'vendor_name' => 'Oxford Rigging Services',
                'category' => 'Equipment',
                'amount' => 520.00,
                'due_date' => now()->format('Y-m-d'),
                'attachment' => $receiptFile,
            ]);

        $bill = Bill::where('club_id', $this->club->id)->firstOrFail();
        $mediaId = $bill->media_id;

        $usageRes = $this->actingAs($this->user)
            ->getJson("/{$this->club->slug}/admin/media/{$mediaId}/usage");

        $usageRes->assertOk()
            ->assertJsonPath('usage_count', 1)
            ->assertJsonPath('usages.0.type', 'Accounting Bill Receipt')
            ->assertJsonPath('usages.0.location', 'Accounting ERP → Purchases');
    }
}
