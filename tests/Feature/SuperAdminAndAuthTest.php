<?php

namespace Tests\Feature;

use App\Models\ClubType;
use App\Models\DefaultEmailTemplate;
use App\Models\User;
use Database\Seeders\ClubTypeSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_only_happens_on_post(): void
    {
        $user = User::factory()->create();

        // 1. POST logout
        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();

        // 2. GET must not log anyone out
        $this->actingAs($user);
        $this->get('/logout')->assertRedirect('/login');
        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_is_redirected_to_login_when_accessing_superadmin(): void
    {
        $response = $this->get('/superadmin');
        $response->assertRedirect('/login');
    }

    public function test_non_superadmin_user_is_redirected_away_from_superadmin(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $response = $this->actingAs($user)->get('/superadmin');
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Unauthorized. Superadmin access required.');
    }

    public function test_superadmin_can_access_superadmin_dashboard_and_pages(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);

        // Seed basic club type for dashboard metrics query
        ClubType::create(['code' => 'craft_lodge', 'name' => 'Craft Lodge', 'available_modules' => []]);

        $response = $this->actingAs($superadmin)->get('/superadmin');
        $response->assertStatus(200);

        $clubTypesPage = $this->actingAs($superadmin)->get('/superadmin/club-types');
        $clubTypesPage->assertStatus(200);

        $emailTemplatesPage = $this->actingAs($superadmin)->get('/superadmin/email-templates');
        $emailTemplatesPage->assertStatus(200);
    }

    public function test_logging_in_lands_on_the_members_home(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com', 'password' => 'secret-pass-123']);

        $this->post('/login', ['email' => 'member@example.com', 'password' => 'secret-pass-123'])
            ->assertRedirect(route('members.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_logging_in_after_being_bounced_returns_to_the_requested_page(): void
    {
        User::factory()->create(['email' => 'member@example.com', 'password' => 'secret-pass-123']);

        $this->get(route('profile.edit'))->assertRedirect('/login');

        $this->post('/login', ['email' => 'member@example.com', 'password' => 'secret-pass-123'])
            ->assertRedirect(route('profile.edit'));
    }

    public function test_login_page_shares_demo_credentials_only_in_local_development(): void
    {
        $this->app['env'] = 'local';
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('demoCredentials.email', 'admin@example.com'));

        $this->app['env'] = 'production';
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('demoCredentials', null));
    }

    public function test_make_me_superadmin_promotes_user_in_local_development(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create(['is_super_admin' => false]);

        $response = $this->actingAs($user)->get('/auth/make-me-superadmin');
        $response->assertRedirect('/superadmin');

        $this->assertTrue($user->fresh()->is_super_admin);
    }

    public function test_superadmin_can_manage_masonic_provinces(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superadmin)->get('/superadmin/provinces');
        $response->assertStatus(200);

        $post = $this->actingAs($superadmin)->post('/superadmin/provinces', [
            'name' => 'Province of Durham Test',
            'code' => 'durham_test',
            'region' => 'North East',
        ]);
        $post->assertRedirect();

        $this->assertDatabaseHas('provinces', ['code' => 'durham_test']);
    }

    public function test_superadmin_can_update_email_templates_and_password_reset_uses_template(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        (new ClubTypeSeeder)->run();

        $template = DefaultEmailTemplate::where('template_key', 'password_reset')->firstOrFail();

        $response = $this->actingAs($superadmin)->put("/superadmin/email-templates/{$template->id}", [
            'subject' => 'CUSTOM: Reset Password for {{member_name}}',
            'body_html' => '<p>Custom Body {{reset_url}}</p>',
        ]);
        $response->assertRedirect();

        $template->refresh();
        $this->assertEquals('CUSTOM: Reset Password for {{member_name}}', $template->subject);

        // Test password reset notification built with updated template
        $user = User::factory()->create(['name' => 'John Doe', 'email' => 'johndoe@example.com']);
        $notification = new ResetPassword('token123');
        $mail = $notification->toMail($user);

        $this->assertEquals('CUSTOM: Reset Password for John Doe', $mail->subject);
        $this->assertStringContainsString('Custom Body', (string) $mail->render());
    }

    public function test_superadmin_can_manage_grand_lodges(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superadmin)->get('/superadmin/grand-lodges');
        $response->assertStatus(200);

        $post = $this->actingAs($superadmin)->post('/superadmin/grand-lodges', [
            'name' => 'Grand Lodge of Scotland Test',
            'code' => 'glos_test',
            'short_name' => 'GLoS Test',
            'country' => 'Scotland',
            'website_url' => 'https://www.grandlodgescotland.com',
        ]);
        $post->assertRedirect();

        $this->assertDatabaseHas('grand_lodges', ['code' => 'glos_test']);
    }
}
