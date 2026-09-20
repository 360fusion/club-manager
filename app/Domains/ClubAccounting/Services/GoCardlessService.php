<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\DirectDebitMandate;
use App\Models\Membership;
use App\Models\User;
use Exception;
use GoCardlessPro\Client;
use GoCardlessPro\Environment;

class GoCardlessService
{
    protected BankAccount $bankAccount;

    protected Client $client;

    public function __construct(BankAccount $bankAccount)
    {
        if (! $bankAccount->gocardless_access_token) {
            throw new Exception('GoCardless Access Token is not configured for this bank account.');
        }
        $this->bankAccount = $bankAccount;

        $env = strtolower($bankAccount->gocardless_environment ?? 'sandbox') === 'live'
            ? Environment::LIVE
            : Environment::SANDBOX;

        $this->client = new Client([
            'access_token' => trim($bankAccount->gocardless_access_token),
            'environment' => $env,
        ]);
    }

    /**
     * Get underlying GoCardless SDK client instance.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Create a GoCardless Redirect Flow URL for Direct Debit mandate signup.
     */
    public function createRedirectFlow(User $user, string $successUrl, ?Membership $membership = null, string $sessionToken = ''): array
    {
        $token = $sessionToken ?: session()->getId();

        try {
            $redirectFlow = $this->client->redirectFlows()->create([
                'params' => [
                    'description' => 'Club Membership Direct Debit Mandate',
                    'session_token' => $token,
                    'success_redirect_url' => $successUrl,
                    'prefilled_customer' => [
                        'given_name' => $user->first_name ?? strtok($user->name ?? '', ' '),
                        'family_name' => $user->last_name ?? (substr(strstr($user->name ?? '', ' '), 1) ?: 'Member'),
                        'email' => $user->email,
                    ],
                ],
            ]);

            return [
                'redirect_flow_id' => $redirectFlow->id,
                'redirect_url' => $redirectFlow->redirect_url,
                'session_token' => $token,
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to create Direct Debit redirect flow: {$e->getMessage()}");
        }
    }

    /**
     * Complete a Redirect Flow after user completes the GoCardless hosted setup.
     */
    public function completeRedirectFlow(string $redirectFlowId, string $sessionToken, User $user, ?Membership $membership = null): DirectDebitMandate
    {
        try {
            $completedFlow = $this->client->redirectFlows()->complete($redirectFlowId, [
                'params' => [
                    'session_token' => $sessionToken,
                ],
            ]);

            $mandateId = $completedFlow->links->mandate ?? null;
            $customerId = $completedFlow->links->customer ?? null;

            if (! $mandateId) {
                throw new Exception('No mandate ID returned from GoCardless redirect flow.');
            }

            $mandateDetails = $this->getMandateDetails($mandateId);

            return DirectDebitMandate::updateOrCreate(
                [
                    'club_id' => $this->bankAccount->club_id,
                    'gocardless_mandate_id' => $mandateId,
                ],
                [
                    'user_id' => $user->id,
                    'membership_id' => $membership?->id,
                    'gocardless_customer_id' => $customerId,
                    'scheme' => $mandateDetails['scheme'] ?? 'bacs',
                    'status' => 'active',
                    'bank_name' => $mandateDetails['bank_name'] ?? 'Direct Debit Bank',
                    'account_holder_name' => $user->name,
                    'account_number_ending' => $mandateDetails['account_number_ending'] ?? 'XX',
                ]
            );
        } catch (Exception $e) {
            throw new Exception("Failed to complete Direct Debit redirect flow: {$e->getMessage()}");
        }
    }

    /**
     * Get specific Mandate details from GoCardless API.
     */
    public function getMandateDetails(string $mandateId): array
    {
        try {
            $mandate = $this->client->mandates()->get($mandateId);
            $bankAccountLink = $mandate->links->customer_bank_account ?? null;

            $bankDetails = [];
            if ($bankAccountLink) {
                $acc = $this->client->customerBankAccounts()->get($bankAccountLink);
                $bankDetails['bank_name'] = $acc->bank_name ?? null;
                $bankDetails['account_number_ending'] = $acc->account_number_ending ?? null;
            }

            return array_merge([
                'scheme' => $mandate->scheme ?? 'bacs',
                'status' => $mandate->status ?? 'active',
            ], $bankDetails);
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Create a Payment request against a stored Mandate.
     */
    public function createPayment(DirectDebitMandate $mandate, int $amountInPence, string $description, ?string $chargeDate = null): array
    {
        $params = [
            'amount' => $amountInPence,
            'currency' => 'GBP',
            'description' => $description,
            'links' => [
                'mandate' => $mandate->gocardless_mandate_id,
            ],
        ];

        if ($chargeDate) {
            $params['charge_date'] = $chargeDate;
        }

        try {
            $payment = $this->client->payments()->create(['params' => $params]);

            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'charge_date' => $payment->charge_date,
            ];
        } catch (Exception $e) {
            throw new Exception("Direct Debit payment creation failed: {$e->getMessage()}");
        }
    }

    /**
     * Cancel an active Direct Debit Mandate.
     */
    public function cancelMandate(DirectDebitMandate $mandate): bool
    {
        try {
            $this->client->mandates()->cancel($mandate->gocardless_mandate_id);
            $mandate->update(['status' => 'cancelled']);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
