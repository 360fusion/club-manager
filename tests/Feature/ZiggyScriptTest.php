<?php

namespace Tests\Feature;

use App\Support\ZiggyScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tighten\Ziggy\BladeRouteGenerator;
use Tighten\Ziggy\Ziggy;

class ZiggyScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_route_table_is_built_once_and_always_carries_the_route_function(): void
    {
        $first = ZiggyScript::render('guest');

        // A long-running worker that already printed one would otherwise get Ziggy's short form without the function.
        BladeRouteGenerator::$generated = true;
        $this->assertSame($first, ZiggyScript::render('guest'));
        $this->assertStringContainsString('const Ziggy', $first);
        $this->assertStringContainsString('"login"', $first);
        $this->assertStringNotContainsString('"superadmin.', $first);
    }

    public function test_a_new_route_gives_a_new_table(): void
    {
        $before = ZiggyScript::render('guest');

        Route::get('/brand-new-page', fn () => 'ok')->name('brand.new');
        Route::getRoutes()->refreshNameLookups();
        Ziggy::clearRoutes(); // Ziggy keeps the route list for the rest of a process; a deploy starts new ones.

        $this->assertNotSame($before, ZiggyScript::render('guest'));
        $this->assertStringContainsString('brand.new', ZiggyScript::render('guest'));
    }

    public function test_pages_carry_the_route_table(): void
    {
        $this->get('/login')->assertOk()->assertSee('const Ziggy', false);
    }
}
