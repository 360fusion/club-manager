<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\GrandLodge;
use App\Models\Province;
use App\Support\MasonicRanks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasonicRanksTest extends TestCase
{
    use RefreshDatabase;

    private function type(string $code): ClubType
    {
        return ClubType::firstOrCreate(['code' => $code], ['name' => $code, 'available_modules' => []]);
    }

    private function club(string $typeCode, array $attributes = []): Club
    {
        static $n = 0;
        $n++;

        return Club::create($attributes + ['club_type_id' => $this->type($typeCode)->id, 'name' => "Club {$n}", 'slug' => "club-{$n}", 'status' => 'active']);
    }

    private function province(array $grandLodge = []): Province
    {
        static $n = 0;
        $n++;

        $gl = GrandLodge::create($grandLodge + ['name' => "Grand Lodge {$n}", 'code' => "gl{$n}", 'country' => 'England']);

        return Province::create(['grand_lodge_id' => $gl->id, 'name' => "Province {$n}", 'code' => "prov{$n}", 'country' => 'England']);
    }

    public function test_the_starter_lists_contain_the_ranks_already_in_use(): void
    {
        $grand = array_column(MasonicRanks::starter('grand'), 'abbreviation');
        $provincial = array_column(MasonicRanks::starter('provincial'), 'abbreviation');

        foreach (['PAGDC', 'PJGD', 'PGSwdB'] as $rank) {
            $this->assertContains($rank, $grand);
        }

        foreach (['PPrGSuptWks', 'PPrSGD', 'PPrGStwd', 'PPrGReg', 'PPrGW', 'PPrGSwdB'] as $rank) {
            $this->assertContains($rank, $provincial);
        }
    }

    public function test_a_new_craft_lodge_gets_its_own_copy_of_the_lists(): void
    {
        $club = $this->club('craft_lodge');

        $this->assertSame(MasonicRanks::starter('grand'), $club->fresh()->settings['grand_ranks']);
        $this->assertSame(MasonicRanks::starter('provincial'), $club->fresh()->settings['provincial_ranks']);
    }

    public function test_a_lodge_created_under_a_province_copies_its_grand_lodges_lists(): void
    {
        $province = $this->province(['grand_ranks' => [['abbreviation' => 'XGR', 'title' => 'Test Grand']], 'provincial_ranks' => [['abbreviation' => 'XPR', 'title' => 'Test Provincial']]]);

        $club = $this->club('craft_lodge', ['province_id' => $province->id]);

        $this->assertSame([['abbreviation' => 'XGR', 'title' => 'Test Grand']], $club->fresh()->settings['grand_ranks']);
        $this->assertSame([['abbreviation' => 'XPR', 'title' => 'Test Provincial']], $club->fresh()->settings['provincial_ranks']);
    }

    public function test_other_masonic_bodies_start_empty_and_other_clubs_get_nothing(): void
    {
        $chapter = $this->club('royal_arch');
        $rowing = $this->club('rowing');

        $this->assertSame([], $chapter->fresh()->settings['grand_ranks']);
        $this->assertArrayNotHasKey('grand_ranks', $rowing->fresh()->settings ?? []);
    }

    public function test_seeding_never_overwrites_a_lodges_own_list(): void
    {
        $club = $this->club('craft_lodge', ['settings' => ['grand_ranks' => [['abbreviation' => 'MINE', 'title' => '']]]]);

        MasonicRanks::seedClub($club->fresh());

        $this->assertSame([['abbreviation' => 'MINE', 'title' => '']], $club->fresh()->settings['grand_ranks']);
        $this->assertArrayHasKey('provincial_ranks', $club->fresh()->settings);
    }

    public function test_a_lodge_without_a_copy_falls_back_to_its_grand_lodge_then_the_starter(): void
    {
        $province = $this->province(['provincial_ranks' => [['abbreviation' => 'XPR', 'title' => '']]]);
        $club = $this->club('craft_lodge', ['province_id' => $province->id]);
        $settings = $club->settings;
        unset($settings['grand_ranks'], $settings['provincial_ranks']);
        $club->forceFill(['settings' => $settings])->saveQuietly();
        $club = $club->fresh();

        $this->assertSame([['abbreviation' => 'XPR', 'title' => '']], MasonicRanks::forClub($club, 'provincial'));
        $this->assertSame(MasonicRanks::starter('grand'), MasonicRanks::forClub($club, 'grand'));
    }

    public function test_dropdown_options_keep_a_rank_the_member_already_holds(): void
    {
        $club = $this->club('craft_lodge', ['settings' => ['grand_ranks' => [['abbreviation' => 'PAGDC', 'title' => 'Past Assistant Grand Director of Ceremonies']]]]);

        $options = MasonicRanks::optionsFor($club->fresh(), 'grand', 'OLDRANK');

        $this->assertSame(['PAGDC', 'OLDRANK'], array_column($options, 'value'));
        $this->assertSame('PAGDC — Past Assistant Grand Director of Ceremonies', $options[0]['label']);
        $this->assertSame('OLDRANK (not in your list)', $options[1]['label']);
        $this->assertSame(['PAGDC'], MasonicRanks::allowedValues($club->fresh(), 'grand'));
    }

    public function test_a_rank_written_as_an_abbreviation_or_title_is_matched_to_the_lists_spelling(): void
    {
        $club = $this->club('craft_lodge')->fresh();

        $this->assertSame('PAGDC', MasonicRanks::normalise($club, 'grand', 'pagdc'));
        $this->assertSame('PAGDC', MasonicRanks::normalise($club, 'grand', 'Past Assistant Grand Director of Ceremonies'));
        $this->assertSame('PPrGSuptWks', MasonicRanks::normalise($club, 'provincial', 'PPr GSuptWks'));
        $this->assertNull(MasonicRanks::normalise($club, 'grand', 'Emperor'));
        $this->assertNull(MasonicRanks::normalise($club, 'grand', ''));
    }

    public function test_lists_are_tidied(): void
    {
        $this->assertSame(
            [['abbreviation' => 'A', 'title' => 'One'], ['abbreviation' => 'B', 'title' => '']],
            MasonicRanks::clean([['abbreviation' => ' A ', 'title' => 'One'], ['abbreviation' => 'a', 'title' => 'Dupe'], ['abbreviation' => '', 'title' => 'Blank'], 'B']),
        );
    }

    public function test_the_backfill_gives_existing_lodges_lists_without_touching_ones_that_have_them(): void
    {
        $bare = $this->club('craft_lodge');
        $mine = $this->club('craft_lodge', ['settings' => ['grand_ranks' => [['abbreviation' => 'MINE', 'title' => '']], 'provincial_ranks' => []]]);
        foreach ([$bare] as $club) {
            $settings = $club->fresh()->settings;
            unset($settings['grand_ranks'], $settings['provincial_ranks']);
            DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);
        }
        $ugle = GrandLodge::create(['name' => 'UGLE', 'code' => 'ugle', 'country' => 'England']);

        $migration = require database_path('migrations/2026_09_23_215302_backfill_masonic_rank_lists.php');
        $migration->up();
        $migration->up();

        $this->assertSame(MasonicRanks::starter('grand'), $bare->fresh()->settings['grand_ranks']);
        $this->assertSame([['abbreviation' => 'MINE', 'title' => '']], $mine->fresh()->settings['grand_ranks']);
        $this->assertSame(MasonicRanks::starter('grand'), $ugle->fresh()->grand_ranks);
    }
}
