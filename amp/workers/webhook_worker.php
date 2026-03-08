<?php

/**
 * AMP Webhook Worker
 *
 * Consumes webhook events from the Redis Stream and sends HTTP POST
 * requests to client webhook URLs concurrently.
 *
 * Retry policy (configurable in config/gateway.php):
 *   attempt 1 → immediate
 *   attempt 2 → 30 seconds
 *   attempt 3 → 2 minutes
 *   attempt 4 → 10 minutes
 *
 * Run: php amp/workers/webhook_worker.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request as AmpRequest;
use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
use App\Services\Security\SignatureService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

use function Amp\async;

$signatureService = app(SignatureService::class);
$httpClient = HttpClientBuilder::buildDefault();

$retryDelays = config('gateway.webhook_retry_delays', [0, 30, 120, 600]);

echo "[PayMock Webhook Worker] Starting...\n";

while (true) {
    $entries = Redis::xread(['webhooks_stream' => '$'], 10, 1000);

    if (empty($entries)) {
        continue;
    }

    foreach ($entries['webhooks_stream'] as $entryId => $data) {
        async(function () use ($data, $httpClient, $signatureService, $retryDelays) {
            $event = WebhookEvent::with('transaction.project.webhooks')
                ->find($data['event_id']);

            if ($event === null) {
                return;
            }

            $webhooks = $event->transaction->project->webhooks()
                ->where('is_active', true)
                ->get();

            if ($webhooks->isEmpty()) {
                $event->status = 'skipped';
                $event->save();

                return;
            }

            foreach ($webhooks as $webhook) {
                $payload = json_encode($event->payload);
                $timestamp = time();
                $signature = $signatureService->sign($payload, (string) $webhook->secret, $timestamp);

                $request = new AmpRequest($webhook->url, 'POST');
                $request->setHeader('Content-Type', 'application/json');
                $request->setHeader('PayMock-Signature', $signature);
                $request->setBody($payload);

                $startedAt = microtime(true);

                try {
                    $response = $httpClient->request($request);
                    $duration = (int) ((microtime(true) - $startedAt) * 1000);

                    $isSuccess = $response->getStatus() >= 200 && $response->getStatus() < 300;

                    WebhookDelivery::create([
                        'event_id' => $event->id,
                        'attempt_number' => $event->attempts + 1,
                        'response_code' => $response->getStatus(),
                        'response_body' => substr($response->getBody()->buffer(), 0, 1000),
                        'is_success' => $isSuccess,
                        'duration_ms' => $duration,
                    ]);

                    if ($isSuccess) {
                        $event->status = 'delivered';
                        $event->save();

                        return;
                    }
                } catch (Throwable $e) {
                    Log::error('Webhook delivery failed', [
                        'event_id' => $event->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Schedule retry if attempts remain
                $attemptIndex = $event->attempts + 1;

                if ($attemptIndex < count($retryDelays)) {
                    $delaySeconds = $retryDelays[$attemptIndex];

                    $event->attempts = $attemptIndex;
                    $event->status = 'retry_scheduled';
                    $event->next_attempt_at = now()->addSeconds($delaySeconds);
                    $event->save();
                } else {
                    $event->status = 'failed';
                    $event->save();
                }
            }
        });
    }
}
