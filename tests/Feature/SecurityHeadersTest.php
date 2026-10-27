<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_policy_is_report_only_until_it_is_switched_on(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_policy_is_enforced_when_switched_on(): void
    {
        config(['app.csp_enforce' => true]);

        $response = $this->get('/login');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_policy_is_enforced_in_production_unless_switched_off(): void
    {
        $default = fn (string $env) => (function () use ($env) {
            $previous = getenv('APP_ENV');
            putenv("APP_ENV={$env}");
            $_ENV['APP_ENV'] = $env;
            $value = (require base_path('config/app.php'))['csp_enforce'];
            putenv($previous === false ? 'APP_ENV' : "APP_ENV={$previous}");
            $_ENV['APP_ENV'] = $previous === false ? 'testing' : $previous;

            return $value;
        })();

        $this->assertTrue($default('production'));
        $this->assertFalse($default('development'));
    }
}
