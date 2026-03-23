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
     * Creates a WebhookEvent record for a status change.
     */
    public function buildAndStore($source, string $eventType): WebhookEvent
    {
        $payload = [
            'id' => $this->tokenGenerator->generateEventId(),
            'object' => 'event',
            'type' => $eventType,
            'created' => now()->timestamp,
            'data' => [
                'object' => $this->mapSourceObject($source),
            ],
        ];

        return WebhookEvent::create([
            'transaction_id' => $source instanceof Transaction ? $source->id : null,
            'payout_id' => $source instanceof \App\Models\Payout ? $source->id : null,
            'public_id' => $this->tokenGenerator->generateEventId(),
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => now(),
        ]);
    }

    private function mapSourceObject($source): array
    {
        if ($source instanceof Transaction) {
            return [
                'id' => $source->public_id,
                'object' => 'payment',
                'amount' => $source->amount,
                'currency' => $source->currency,
                'status' => $source->status,
                'method' => $source->method,
                'created' => $source->created_at->timestamp,
            ];
        }

        if ($source instanceof \App\Models\Payout) {
            return [
                'id' => $source->id, // Payout uses custom ID by default or just the id field
                'object' => 'payout',
                'amount' => $source->amount,
                'status' => $source->status,
                'transfer_details' => $source->transfer_details,
                'created' => $source->created_at->timestamp,
            ];
        }

        return [];
    }
}
