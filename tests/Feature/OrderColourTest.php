<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Support\OrderColours;
use Database\Seeders\ClubTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderColourTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_php_and_javascript_palettes_list_the_same_colours(): void
    {
        preg_match_all('/^    (\w+): \{ label:/m', file_get_contents(resource_path('js/Utils/orderColour.js')), $matches);

        $this->assertEqualsCanonicalizing(OrderColours::KEYS, $matches[1]);
    }

    public function test_the_well_known_orders_have_their_traditional_colours(): void
    {
        $this->assertSame('sky', OrderColours::for('craft_lodge'));
        $this->assertSame('red', OrderColours::for('royal_arch'));
        $this->assertSame('pink', OrderColours::for('rose_croix'));
        $this->assertSame('purple', OrderColours::for('red_cross_constantine'));
        $this->assertSame('slate', OrderColours::for('knights_templar'));
        $this->assertSame('emerald', OrderColours::for('allied_masonic'));
        $this->assertSame(OrderColours::DEFAULT, OrderColours::for('something_new'));
    }

    public function test_seeded_orders_get_distinct_valid_colours(): void
    {
        (new ClubTypeSeeder)->run();

        $colours = ClubType::pluck('colour', 'code');

        $this->assertNotEmpty($colours);
        foreach ($colours as $code => $colour) {
            $this->assertTrue(OrderColours::isValid($colour), "{$code} has an invalid colour {$colour}");
        }
        $this->assertSame($colours->count(), $colours->unique()->count(), 'Two orders share a colour');
    }

    public function test_reseeding_does_not_overwrite_a_colour_a_super_admin_changed(): void
    {
        (new ClubTypeSeeder)->run();
        ClubType::where('code', 'craft_lodge')->update(['colour' => 'teal']);

        (new ClubTypeSeeder)->run();

        $this->assertSame('teal', ClubType::where('code', 'craft_lodge')->value('colour'));
    }

    public function test_a_super_admin_can_change_an_orders_colour(): void
    {
        $type = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'available_modules' => [], 'default_settings' => []]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->put(route('superadmin.club_types.update', $type->id), ['name' => 'Craft Lodge', 'colour' => 'pink'])
            ->assertSessionHasNoErrors();

        $this->assertSame('pink', $type->fresh()->colour);
    }

    public function test_an_unknown_colour_is_rejected_and_omitting_it_keeps_the_current_one(): void
    {
        $type = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'colour' => 'sky', 'available_modules' => [], 'default_settings' => []]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->put(route('superadmin.club_types.update', $type->id), ['name' => 'Craft Lodge', 'colour' => 'hotpink'])
            ->assertSessionHasErrors('colour');
        $this->assertSame('sky', $type->fresh()->colour);

        $this->put(route('superadmin.club_types.update', $type->id), ['name' => 'Craft Lodge (renamed)'])->assertSessionHasNoErrors();
        $this->assertSame('sky', $type->fresh()->colour);
    }

    public function test_a_new_order_defaults_to_its_known_colour_and_others_cannot_change_colours(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->post(route('superadmin.club_types.store'), ['name' => 'Royal Arch Chapter', 'code' => 'royal_arch'])
            ->assertSessionHasNoErrors();
        $this->assertSame('red', ClubType::where('code', 'royal_arch')->value('colour'));

        $type = ClubType::where('code', 'royal_arch')->first();
        $ordinary = User::factory()->create();

        $this->actingAs($ordinary)->put(route('superadmin.club_types.update', $type->id), ['name' => 'x', 'colour' => 'blue'])->assertRedirect();
        $this->assertSame('red', $type->fresh()->colour);
    }

    public function test_member_pages_carry_each_clubs_order_colour(): void
    {
        $craft = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'colour' => 'sky', 'available_modules' => [], 'default_settings' => []]);
        $chapter = ClubType::create(['name' => 'Royal Arch Chapter', 'code' => 'royal_arch', 'colour' => 'red', 'available_modules' => [], 'default_settings' => []]);
        $lodge = Club::create(['club_type_id' => $craft->id, 'name' => 'Oxford Lodge', 'slug' => 'oxford-lodge', 'status' => 'active']);
        $arch = Club::create(['club_type_id' => $chapter->id, 'name' => 'Oxford Chapter', 'slug' => 'oxford-chapter', 'status' => 'active']);

        $member = User::factory()->create();
        $lodge->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $arch->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $author = User::factory()->create();
        Post::create(['club_id' => $lodge->id, 'author_id' => $author->id, 'title' => 'Lodge news', 'slug' => 'lodge-news', 'content' => '', 'status' => 'published']);
        Event::create(['club_id' => $arch->id, 'title' => 'Chapter dinner', 'slug' => 'chapter-dinner', 'starts_at' => now()->addDays(3), 'status' => 'upcoming']);

        $this->actingAs($member);

        $this->get('/members/news')->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.club.colour', 'sky')
            ->where('clubOptions', fn ($options) => collect($options)->pluck('colour', 'slug')->all() === ['oxford-lodge' => 'sky', 'oxford-chapter' => 'red']));

        $this->get('/members/events')->assertInertia(fn (Assert $page) => $page->where('events.0.club.colour', 'red'));

        $this->get('/members/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('clubs', fn ($clubs) => collect($clubs)->pluck('colour', 'slug')->all() === ['oxford-lodge' => 'sky', 'oxford-chapter' => 'red'])
            ->where('auth.clubs', fn ($clubs) => collect($clubs)->pluck('colour')->sort()->values()->all() === ['red', 'sky']));
    }
}
