<?php

namespace App\Services\Payments;

use App\Models\Project;
use App\Models\Transaction;
use App\Services\Security\TokenGenerator;
use App\Services\Webhooks\WebhookDispatcher;
use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Engine\SimulationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core payment orchestration service.
 *
 * Flow:
 *  1. Build SimulationContext from request data
 *  2. Run SimulationEngine → get SimulationDecision
 *  3. Apply delay from decision (if any) — delegated to AMP in production
 *  4. Persist transaction with final status
 *  5. Dispatch webhook event
 *  6. Return transaction
 */
final class PaymentService
{
    public function __construct(
        private readonly SimulationEngine $engine,
        private readonly TokenGenerator $tokenGenerator,
        private readonly WebhookDispatcher $webhookDispatcher,
        private readonly QrCodeService $qrCodeService,
        private readonly \App\Services\Balances\BalanceService $balanceService,
    ) {
    }

    /**
     * Creates and processes a new transaction.
     *
     * @param array{
     *   amount: float,
     *   currency: string,
     *   method: string,
     *   description?: string,
     *   customer_name?: string,
     *   customer_email?: string,
     *   card_number?: string,
     *   metadata?: array<string, mixed>,
     *   idempotency_key?: string,
     *   forced_rule?: string,
     * } $data
     */
    public function createPayment(Project $project, array $data): Transaction
    {
        // Guard: check idempotency key before processing
        if (isset($data['idempotency_key'])) {
            $existing = $this->findByIdempotencyKey($project, $data['idempotency_key']);

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($project, $data): Transaction {
            $transaction = $this->createPendingTransaction($project, $data);

            $context = $this->buildContext($transaction, $data);

            $decision = $this->engine->process($context);

            $this->applyDecision($transaction, $decision);

            // Update Ledger/Balance if approved
            if ($transaction->status === 'approved') {
                $this->balanceService->credit(
                    $project,
                    (float) $transaction->amount,
                    'pending',
                    "Payment Received: {$transaction->public_id}",
                    $transaction
                );
            }

            $eventType = 'payment.' . $transaction->status;

            $isDuplicate = $decision->hasSideEffect('duplicate_webhook');

            $this->webhookDispatcher->dispatch($transaction, $eventType, $isDuplicate);

            Log::info('Payment processed', [
                'transaction_id' => $transaction->public_id,
                'status' => $transaction->status,
                'rule' => $decision->rule,
            ]);

            return $transaction;
        });
    }

    private function createPendingTransaction(Project $project, array $data): Transaction
    {
        $transaction = Transaction::create([
            'project_id' => $project->id,
            'public_id' => $this->tokenGenerator->generatePaymentId(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'BRL',
            'method' => $data['method'],
            'status' => 'pending',
            'description' => $data['description'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'card_last4' => isset($data['card_number']) ? substr($data['card_number'], -4) : null,
            'metadata' => $data['metadata'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        // Generate QR code for pix/qrcode methods
        if (in_array($data['method'], ['pix', 'qrcode'], strict: true)) {
            $transaction->qr_code = $this->qrCodeService->generateQrCodeSvg($transaction);
            $transaction->qr_code_url = $this->qrCodeService->generateUrl($transaction);
            $transaction->save();
        }

        return $transaction;
    }

    private function buildContext(Transaction $transaction, array $data): SimulationContext
    {
        return new SimulationContext(
            transactionId: $transaction->id,
            amount: (float) $transaction->amount,
            currency: $transaction->currency,
            method: $transaction->method,
            cardNumber: $data['card_number'] ?? null,
            customerName: $transaction->customer_name,
            customerEmail: $transaction->customer_email,
            timestamp: new \DateTimeImmutable(),
            forcedRule: $data['forced_rule'] ?? null,
        );
    }

    private function applyDecision(Transaction $transaction, SimulationDecision $decision): void
    {
        $transaction->status = $decision->status;
        $transaction->failure_reason = $decision->reason;
        $transaction->simulation_rule = $decision->rule;
        $transaction->save();
    }

    private function findByIdempotencyKey(Project $project, string $key): ?Transaction
    {
        return Transaction::query()
            ->where('project_id', $project->id)
            ->where('idempotency_key', $key)
            ->first();
    }
}
