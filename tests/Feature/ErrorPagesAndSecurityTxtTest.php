<?php

namespace Tests\Feature;

use App\Models\Club;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesAndSecurityTxtTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_error_page_renders_with_its_own_wording(): void
    {
        $titles = [403 => 'You cannot open this page', 404 => 'Page not found', 419 => 'This page has expired', 429 => 'Slow down a little', 500 => 'Something went wrong on our side', 503 => 'Back soon'];

        // The handler registers the errors:: view namespace only while rendering an error; do the same by hand.
        (new RegisterErrorViewPaths)();

        foreach ($titles as $status => $title) {
            $html = view("errors::{$status}", ['exception' => new HttpException($status)])->render();

            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString('noindex', $html);
        }
    }

    public function test_an_unknown_address_gets_the_friendly_not_found_page(): void
    {
        $this->get('/members/no-such-club/no-such-page')->assertNotFound();
        $this->get('/a/b/c/d/e')->assertNotFound()->assertSee('Page not found')->assertSee('Go to the home page');
    }

    public function test_a_403_shows_the_reason_given_but_never_as_markup(): void
    {
        Route::get('/_test/forbidden', fn () => abort(403, '<b>Only the treasurer</b>'));

        $this->get('/_test/forbidden')->assertForbidden()
            ->assertSee('&lt;b&gt;Only the treasurer&lt;/b&gt;', false)
            ->assertDontSee('<b>Only the treasurer</b>', false);
    }

    public function test_a_server_error_never_shows_the_exception_message(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/boom', fn () => throw new \RuntimeException('password=hunter2 for db'));

        $this->get('/_test/boom')->assertStatus(500)->assertSee('Something went wrong on our side')->assertDontSee('hunter2');
    }

    public function test_a_missing_record_does_not_leak_the_model_name_on_the_404_page(): void
    {
        Route::get('/_test/missing', fn () => Club::findOrFail(999999));

        $this->get('/_test/missing')->assertNotFound()->assertDontSee('App\\Models\\Club')->assertDontSee('No query results');
    }

    public function test_security_txt_is_not_served_until_a_contact_is_set(): void
    {
        config(['app.security_contact' => null]);

        $this->get('/.well-known/security.txt')->assertNotFound();
    }

    public function test_security_txt_names_the_contact_and_expires_in_the_future(): void
    {
        config(['app.security_contact' => 'security@example.test']);

        $response = $this->get('/.well-known/security.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString('Contact: mailto:security@example.test', $body);
        preg_match('/^Expires: (.+)$/m', $body, $m);
        $this->assertTrue(Carbon::parse($m[1])->isAfter(now()->addMonths(5)));
    }
}
