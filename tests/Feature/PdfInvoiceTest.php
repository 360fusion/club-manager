<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_invoice_html_view(): void
    {
        $type = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['invoices'],
        ]);

        $club = Club::create([
            'club_type_id' => $type->id,
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-9999',
            'club_id' => $club->id,
            'user_id' => $user->id,
            'title' => 'Annual Dues 2026',
            'amount' => 45.00,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.download', [
                'slug' => $club->slug,
                'id' => $invoice->id,
                'format' => 'html',
            ]));

        $response->assertStatus(200);
        $response->assertSee('INV-2026-9999');
        $response->assertSee('The Lodge of Fraternity');
    }
}
