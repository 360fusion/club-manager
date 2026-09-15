<?php

namespace Tests\Feature;

use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingContactTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Boating Club',
            'code' => 'boating',
            'available_modules' => ['posts'],
        ]);

        $this->club = Club::create([
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->clubs()->attach($this->club, ['role' => 'admin']);
    }

    public function test_authenticated_admin_can_create_person_contact(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.accounting.contacts.store', $this->club->slug), [
            'type' => 'person',
            'name' => 'Dr. Alexander Vance',
            'contact_person' => 'Alex Vance',
            'email' => 'alex.vance@oxford.ac.uk',
            'phone' => '+44 7700 900888',
            'role' => 'Contractor / Coach',
            'tax_id' => 'UTR 887766',
            'address_line_1' => '42 High Street',
            'city' => 'Oxford',
            'postcode' => 'OX1 4BG',
            'country' => 'United Kingdom',
            'notes' => 'Senior coaching contractor.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('accounting_contacts', [
            'club_id' => $this->club->id,
            'type' => 'person',
            'name' => 'Dr. Alexander Vance',
            'email' => 'alex.vance@oxford.ac.uk',
            'role' => 'Contractor / Coach',
        ]);
    }

    public function test_authenticated_admin_can_create_business_contact(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.accounting.contacts.store', $this->club->slug), [
            'type' => 'business',
            'name' => 'Highfield Boatyard & Chandlery',
            'contact_person' => 'Mark Highfield',
            'email' => 'orders@highfieldboatyard.co.uk',
            'phone' => '+44 1865 778899',
            'role' => 'Vendor / Supplier',
            'tax_id' => 'GB 991 2233 44',
            'address_line_1' => 'Dock 12 River Way',
            'city' => 'Oxford',
            'postcode' => 'OX4 4JY',
            'country' => 'United Kingdom',
            'notes' => 'Equipment vendor.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('accounting_contacts', [
            'club_id' => $this->club->id,
            'type' => 'business',
            'name' => 'Highfield Boatyard & Chandlery',
            'contact_person' => 'Mark Highfield',
            'role' => 'Vendor / Supplier',
        ]);
    }
}
