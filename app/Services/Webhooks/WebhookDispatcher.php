<?php

namespace App\Services\Webhooks;

use App\Models\Transaction;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Dispatches webhook events to Redis Stream for AMP workers to consume.
 * Also handles side-effects like duplicate webhook sending.
 */
final class WebhookDispatcher
{
    public function __construct(
        private readonly WebhookPayloadBuilder $payloadBuilder,
    ) {
    }

    public function dispatch(Transaction $transaction, string $eventType, bool $duplicate = false): void
    {
        $event = $this->payloadBuilder->buildAndStore($transaction, $eventType);

        $this->pushToStream($event);

        if ($duplicate) {
            // Duplicate webhook — push again for idempotency testing
            $this->pushToStream($event);

            Log::info('Duplicate webhook dispatched', [
                'event_id' => $event->public_id,
                'event_type' => $eventType,
            ]);
        }
    }

    private function pushToStream(WebhookEvent $event): void
    {
        Redis::xadd('webhooks_stream', '*', [
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'transaction_id' => $event->transaction_id,
        ]);
    }
}
