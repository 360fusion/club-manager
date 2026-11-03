<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ReservedClubSlugs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiTokenPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_is_made_with_an_expiry_and_its_value_is_shown_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.tokens.store'), ['name' => 'my phone', 'days' => 90]);
        $response->assertSessionHas('new_token');

        $token = $user->tokens()->first();
        $this->assertSame('my phone', $token->name);
        $this->assertSame(['profile:read'], $token->abilities);
        $this->assertTrue($token->expires_at->isSameDay(now()->addDays(90)));

        // The redirect back shows it; what is stored is only a hash, so it can never be shown again.
        $plain = $response->getSession()->get('new_token');
        $this->actingAs($user)->get(route('account.tokens.index'))->assertInertia(fn ($page) => $page->where('newToken', $plain));
        $this->assertStringNotContainsString(explode('|', $plain)[1], json_encode(DB::table('personal_access_tokens')->get()));
    }

    public function test_an_expiry_outside_the_offered_choices_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.tokens.store'), ['name' => 'x', 'days' => 100000])->assertSessionHasErrors('days');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_account_with_two_factor_cannot_make_a_token_here_either(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $this->actingAs($user)->post(route('account.tokens.store'), ['name' => 'x', 'days' => 30])->assertSessionHasErrors('name');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_person_can_revoke_their_own_token_and_never_someone_elses(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $myToken = $mine->createToken('mine')->accessToken;
        $theirToken = $theirs->createToken('theirs')->accessToken;

        $this->actingAs($mine)->delete(route('account.tokens.destroy', $theirToken->id));
        $this->assertSame(1, $theirs->tokens()->count());

        $this->actingAs($mine)->delete(route('account.tokens.destroy', $myToken->id));
        $this->assertSame(0, $mine->tokens()->count());
    }

    public function test_a_token_made_by_the_sign_in_endpoint_is_listed_and_a_revoked_one_stops_working(): void
    {
        $user = User::factory()->create(['password' => bcrypt('right-password')]);

        $plain = $this->postJson(route('api.tokens.create'), ['email' => $user->email, 'password' => 'right-password', 'device_name' => 'app'])->json('token');
        $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$plain}"])->assertOk();

        $this->actingAs($user)->get(route('account.tokens.index'))->assertInertia(fn ($page) => $page->where('tokens.0.name', 'app'));
        $this->actingAs($user)->delete(route('account.tokens.destroy', $user->tokens()->first()->id));

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$plain}"])->assertUnauthorized();
    }

    public function test_the_page_is_for_signed_in_people_and_the_address_cannot_be_a_club_name(): void
    {
        $this->get(route('account.tokens.index'))->assertRedirect();
        $this->assertTrue(ReservedClubSlugs::isReserved('api-tokens'));
    }
}
