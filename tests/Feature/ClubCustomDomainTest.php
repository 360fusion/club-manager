<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClubCustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN = 'www.lodge-of-fraternity.example';

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create(['slug' => 'lodge-of-fraternity', 'custom_domain' => self::DOMAIN, 'domain_status' => 'active']);
        $this->page($this->club, 'history', 'Our History');
    }

    private function page(Club $club, string $slug, string $title): Page
    {
        return Page::create([
            'club_id' => $club->id,
            'title' => $title,
            'slug' => $slug,
            'is_published' => true,
            'blocks' => [['type' => 'text', 'content' => '<p>Hello</p>']],
        ]);
    }

    private function redirectToDomain(bool $on): void
    {
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], ['site_redirect_to_domain' => $on])]);
    }

    public function test_an_active_domain_serves_the_lodge_page_at_its_clean_address(): void
    {
        $this->get('http://'.self::DOMAIN.'/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Site')
                ->where('page.title', 'Our History')
                ->where('onClubDomain', true)
                ->url('/history'));
    }

    public function test_a_pending_domain_does_not_serve_the_site(): void
    {
        $this->club->update(['domain_status' => 'pending']);

        $this->get('http://'.self::DOMAIN.'/history')->assertNotFound();
    }

    public function test_a_lodge_domain_never_serves_another_lodges_pages(): void
    {
        $other = Club::factory()->create(['slug' => 'bath-lodge']);
        $this->page($other, 'charity', 'Bath Charity');

        $this->get('http://'.self::DOMAIN.'/charity')->assertNotFound();
        $this->get('http://'.self::DOMAIN.'/site/bath-lodge/charity')->assertNotFound();
    }

    public function test_an_old_site_address_on_the_domain_forwards_to_the_clean_address(): void
    {
        $this->get('http://'.self::DOMAIN.'/site/lodge-of-fraternity/history?ref=news')
            ->assertStatus(301)
            ->assertRedirect('/history?ref=news');
    }

    public function test_signing_in_on_a_lodge_domain_goes_to_the_main_address(): void
    {
        $this->get('http://'.self::DOMAIN.'/login')
            ->assertRedirect(rtrim(config('app.url'), '/').'/login');
    }

    public function test_the_main_address_keeps_the_pages_unless_the_lodge_sends_visitors_to_its_domain(): void
    {
        $this->get('/site/lodge-of-fraternity/history')->assertOk();

        $this->redirectToDomain(true);

        $this->get('/site/lodge-of-fraternity/history')
            ->assertStatus(301)
            ->assertRedirect('https://'.self::DOMAIN.'/history');
        $this->get('/site/lodge-of-fraternity')->assertRedirect('https://'.self::DOMAIN.'/');
    }

    public function test_the_main_address_is_not_forwarded_while_the_domain_is_pending_or_for_a_theme_preview(): void
    {
        $this->redirectToDomain(true);

        $this->get('/site/lodge-of-fraternity/history?preview_theme=classic')->assertOk();

        $this->club->update(['domain_status' => 'pending']);

        $this->get('/site/lodge-of-fraternity/history')->assertOk();
    }

    public function test_the_sitemap_lists_pages_on_the_lodge_domain(): void
    {
        $this->get('/site/lodge-of-fraternity/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>https://'.self::DOMAIN.'/history</loc>', false);
    }

    public function test_caddy_may_issue_a_certificate_only_for_an_active_domain(): void
    {
        Club::factory()->create(['custom_domain' => 'pending.example', 'domain_status' => 'pending']);

        $this->get('/internal/domain-allowed?domain='.self::DOMAIN)->assertOk();
        $this->get('/internal/domain-allowed?domain=pending.example')->assertNotFound();
        $this->get('/internal/domain-allowed?domain=unknown.example')->assertNotFound();
    }

    public function test_the_certificate_check_refuses_callers_outside_the_server(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->get('/internal/domain-allowed?domain='.self::DOMAIN)
            ->assertNotFound();
    }

    public function test_an_admin_can_choose_to_send_visitors_to_the_domain(): void
    {
        $admin = User::factory()->create();
        $this->club->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.pages.settings.update', ['clubSlug' => $this->club->slug]), [
                'custom_domain' => self::DOMAIN,
                'site_redirect_to_domain' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->club->fresh()->settings['site_redirect_to_domain']);
        $this->assertSame('active', $this->club->fresh()->domain_status);
    }
}
