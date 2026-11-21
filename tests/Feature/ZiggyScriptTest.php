<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ZiggyScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tighten\Ziggy\Ziggy;

class ZiggyScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_load_the_route_table_as_a_kept_file_instead_of_carrying_it(): void
    {
        $src = route('ziggy.routes', ['group' => 'guest', 'hash' => ZiggyScript::hash()], false);

        $this->get('/login')->assertOk()->assertSee('<script src="'.$src.'"></script>', false)->assertDontSee('const Ziggy', false);

        $file = $this->get($src)->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->assertStringContainsString('immutable', $file->headers->get('Cache-Control'));
        $this->assertStringStartsWith('const Ziggy = {', $file->getContent());
        $this->assertStringContainsString('"login"', $file->getContent());
        $this->assertStringNotContainsString('"superadmin.', $file->getContent());
        $this->assertStringContainsString('route', substr($file->getContent(), -2000), 'the global route() function page scripts call');
    }

    public function test_only_the_visitors_own_group_is_served(): void
    {
        $hash = ZiggyScript::hash();

        $this->get(route('ziggy.routes', ['group' => 'staff', 'hash' => $hash]))->assertNotFound();
        $this->get(route('ziggy.routes', ['group' => 'all', 'hash' => $hash]))->assertNotFound();

        $this->actingAs(User::factory()->create())->get(route('ziggy.routes', ['group' => 'member', 'hash' => $hash]))->assertOk();
    }

    public function test_an_old_address_gets_todays_table_without_being_kept(): void
    {
        $response = $this->get(route('ziggy.routes', ['group' => 'guest', 'hash' => str_repeat('0', 16)]))->assertOk();

        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    public function test_a_new_route_gives_a_new_address(): void
    {
        $before = ZiggyScript::hash();

        Route::get('/brand-new-page', fn () => 'ok')->name('brand.new');
        Route::getRoutes()->refreshNameLookups();
        Ziggy::clearRoutes(); // Ziggy keeps the route list for the rest of a process; a deploy starts new ones.

        $this->assertNotSame($before, ZiggyScript::hash());
        $this->assertStringContainsString('brand.new', ZiggyScript::body('guest'));
    }

    public function test_another_address_such_as_a_lodges_own_domain_keeps_the_table_in_the_page(): void
    {
        $this->get('http://lodge-domain.test/login')->assertOk()->assertSee('const Ziggy', false);
    }
}
