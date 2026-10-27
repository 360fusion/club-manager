<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListBorrowedClubDetailsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_clubs_and_meetings_that_still_hold_the_old_defaults_and_changes_nothing(): void
    {
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $old = Club::create(['club_type_id' => $type->id, 'name' => 'Old Defaults Lodge', 'slug' => 'old-defaults', 'status' => 'active', 'lodge_number' => '1418', 'motto' => 'Fraternus Amor Maneto']);
        $own = Club::create(['club_type_id' => $type->id, 'name' => 'Own Details Lodge', 'slug' => 'own-details', 'status' => 'active', 'lodge_number' => '357', 'motto' => 'Unity']);

        Meeting::create(['club_id' => $old->id, 'title' => 'M', 'meeting_date' => '2026-10-20', 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Suit', 'rsvp_cutoff_at' => now()->addDays(5), 'officers_year_label' => 'OFFICERS FOR 2025-2026']);

        $this->artisan('clubs:borrowed-details')
            ->expectsOutputToContain('Old Defaults Lodge')
            ->doesntExpectOutputToContain('Own Details Lodge')
            ->assertSuccessful();

        $this->assertSame('1418', $old->fresh()->lodge_number);
        $this->assertSame('357', $own->fresh()->lodge_number);
    }

    public function test_it_says_so_when_nothing_holds_the_old_values(): void
    {
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        Club::create(['club_type_id' => $type->id, 'name' => 'Own Details Lodge', 'slug' => 'own-details', 'status' => 'active', 'lodge_number' => '357']);

        $this->artisan('clubs:borrowed-details')->expectsOutput('No club or meeting holds those values.')->assertSuccessful();
    }

    public function test_clear_empties_the_old_values_except_for_the_club_named_and_only_after_confirmation(): void
    {
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $borrowed = Club::create(['club_type_id' => $type->id, 'name' => 'Borrowed Lodge', 'slug' => 'borrowed', 'status' => 'active', 'lodge_number' => '1418', 'motto' => 'Fraternus Amor Maneto']);
        $real = Club::create(['club_type_id' => $type->id, 'name' => 'Real 1418 Lodge', 'slug' => 'real-1418', 'status' => 'active', 'lodge_number' => '1418', 'motto' => 'Fraternus Amor Maneto']);
        $meeting = Meeting::create(['club_id' => $borrowed->id, 'title' => 'M', 'meeting_date' => '2026-10-20', 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Suit', 'rsvp_cutoff_at' => now()->addDays(5), 'officers_year_label' => 'OFFICERS FOR 2025-2026']);

        $this->artisan('clubs:borrowed-details', ['--clear' => true, '--except' => ['real-1418']])->expectsConfirmation('Empty these values on every club and meeting listed except real-1418?', 'no')->assertSuccessful();
        $this->assertSame('1418', $borrowed->fresh()->lodge_number);

        $this->artisan('clubs:borrowed-details', ['--clear' => true, '--except' => ['real-1418']])->expectsConfirmation('Empty these values on every club and meeting listed except real-1418?', 'yes')->assertSuccessful();

        $this->assertNull($borrowed->fresh()->lodge_number);
        $this->assertNull($borrowed->fresh()->motto);
        $this->assertNull($meeting->fresh()->officers_year_label);
        $this->assertSame('1418', $real->fresh()->lodge_number);
        $this->assertSame('Fraternus Amor Maneto', $real->fresh()->motto);
    }

    public function test_force_clears_without_asking_for_use_where_nobody_can_answer_a_prompt(): void
    {
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $borrowed = Club::create(['club_type_id' => $type->id, 'name' => 'Borrowed Lodge', 'slug' => 'borrowed', 'status' => 'active', 'lodge_number' => '1418']);
        $real = Club::create(['club_type_id' => $type->id, 'name' => 'Real Lodge', 'slug' => 'real-1418', 'status' => 'active', 'lodge_number' => '1418']);

        $this->artisan('clubs:borrowed-details', ['--clear' => true, '--force' => true, '--except' => ['real-1418'], '--no-interaction' => true])->assertSuccessful();

        $this->assertNull($borrowed->fresh()->lodge_number);
        $this->assertSame('1418', $real->fresh()->lodge_number);
    }
}
