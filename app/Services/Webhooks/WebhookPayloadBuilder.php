<?php

namespace App\Services\Webhooks;

use App\Models\Transaction;
use App\Models\WebhookEvent;
use App\Services\Security\TokenGenerator;

/**
 * Builds webhook payloads in the Stripe event format and persists them.
 * The actual HTTP delivery is handled by the AMP webhook worker.
 */
final class WebhookPayloadBuilder
{
    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * Creates a WebhookEvent record for the given transaction status change.
     */
    public function buildAndStore(Transaction $transaction, string $eventType): WebhookEvent
    {
        $payload = [
            'id' => $this->tokenGenerator->generateEventId(),
            'object' => 'event',
            'type' => $eventType,
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => $transaction->public_id,
                    'object' => 'payment',
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'method' => $transaction->method,
                    'created' => $transaction->created_at->timestamp,
                ],
            ],
        ];

        return WebhookEvent::create([
            'transaction_id' => $transaction->id,
            'public_id' => $this->tokenGenerator->generateEventId(),
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => now(),
        ]);
    }
}
