<?php

namespace App\Services\Security;

use Illuminate\Support\Str;

/**
 * Generates opaque, prefixed identifiers matching the Stripe style.
 * Examples: pay_3Np9cJfA, proj_f82fa3e9, sk_test_xxxxxxxxxxx
 */
final class TokenGenerator
{
    public function generateChargeId(): string
    {
        return $this->withPrefix(config('gateway.prefixes.charge'));
    }

    public function generatePaymentId(): string
    {
        return $this->withPrefix(config('gateway.prefixes.payment'));
    }

    public function generateProjectId(): string
    {
        return $this->withPrefix(config('gateway.prefixes.project'));
    }

    public function generateApiKey(): string
    {
        return config('gateway.prefixes.api_key') . Str::random(24);
    }

    public function generatePublicKey(): string
    {
        return config('gateway.prefixes.public_key') . Str::random(24);
    }

    public function generateWebhookId(): string
    {
        return $this->withPrefix(config('gateway.prefixes.webhook'));
    }

    public function generateEventId(): string
    {
        return $this->withPrefix(config('gateway.prefixes.event'));
    }

    public function generateBalanceTransactionId(): string
    {
        return $this->withPrefix(config('gateway.prefixes.balance_transaction'));
    }

    public function generatePayoutId(): string
    {
        return $this->withPrefix(config('gateway.prefixes.payout'));
    }

    private function withPrefix(string $prefix): string
    {
        return $prefix . Str::random(12);
    }
}
