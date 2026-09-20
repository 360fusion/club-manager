<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\BankAccount;
use App\Models\Club;
use App\Models\ClubType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoCardlessWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'code' => 'craft_lodge',
            'name' => 'Craft Lodge',
            'available_modules' => [],
        ]);

        $this->club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Test Lodge',
            'slug' => 'test-lodge',
        ]);
    }

    private function bankAccount(?string $webhookSecret): BankAccount
    {
        return BankAccount::create([
            'club_id' => $this->club->id,
            'bank_name' => 'GoCardless',
            'account_name' => 'Direct Debits',
            'gocardless_access_token' => 'token_123',
            'gocardless_webhook_secret' => $webhookSecret,
        ]);
    }

    private function payload(): array
    {
        return ['events' => [[
            'resource_type' => 'mandates',
            'action' => 'active',
            'links' => ['mandate' => 'MD123'],
        ]]];
    }

    public function test_webhook_is_reachable_without_authentication(): void
    {
        $this->bankAccount('shhh');

        $response = $this->postJson("/webhooks/gocardless/{$this->club->id}", $this->payload());

        $response->assertStatus(498);
    }

    public function test_webhook_is_rejected_when_no_secret_is_configured(): void
    {
        $this->bankAccount(null);

        $response = $this->postJson("/webhooks/gocardless/{$this->club->id}", $this->payload());

        $response->assertForbidden();
    }

    public function test_webhook_is_rejected_when_the_signature_does_not_match(): void
    {
        $this->bankAccount('shhh');

        $response = $this->postJson(
            "/webhooks/gocardless/{$this->club->id}",
            $this->payload(),
            ['Webhook-Signature' => 'not-the-right-signature'],
        );

        $response->assertStatus(498);
    }

    public function test_webhook_is_accepted_when_the_signature_matches(): void
    {
        $secret = 'shhh';
        $this->bankAccount($secret);

        $body = json_encode($this->payload());
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->call(
            'POST',
            "/webhooks/gocardless/{$this->club->id}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_WEBHOOK_SIGNATURE' => $signature, 'HTTP_ACCEPT' => 'application/json'],
            $body,
        );

        $response->assertSuccessful();
    }
}
