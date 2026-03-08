<?php

namespace App\Services\Balances;

use App\Models\Project;
use Exception;

class AdvanceService
{
    public function __construct(
        protected BalanceService $balanceService
    ) {
    }

    /**
     * Predefined fee plans based on user requirements.
     */
    public function getAdvanceOptions(): array
    {
        return [
            ['days' => 30, 'fee_percentage' => 0],
            ['days' => 15, 'fee_percentage' => 1.5],
            ['days' => 7, 'fee_percentage' => 3.0],
            ['days' => 3, 'fee_percentage' => 4.0],
            ['days' => 1, 'fee_percentage' => 5.0], // 24 hours
            ['days' => 0.04, 'fee_percentage' => 6.0], // 1 hour (approx)
            ['days' => 0, 'fee_percentage' => 10.0], // Instant
        ];
    }

    /**
     * Executes an anticipation request for a specific amount.
     */
    public function advance(Project $project, float $amount, int $days): array
    {
        $option = collect($this->getAdvanceOptions())->firstWhere('days', $days);

        if (!$option) {
            throw new Exception("Invalid advance timeframe: {$days} days.");
        }

        $balance = $this->balanceService->getOrCreateBalance($project);

        if ($balance->pending < $amount) {
            throw new Exception("Insufficient pending balance for advance.");
        }

        $feePercentage = $option['fee_percentage'];
        $feeAmount = round($amount * ($feePercentage / 100), 2);
        $netAmount = $amount - $feeAmount;

        // Execute Settlement via Ledger
        $this->balanceService->settle($project, $amount, "Advance Request ({$days} days)", null);

        // Debit the Fee from Available (since settle moves the whole amount to available)
        if ($feeAmount > 0) {
            $this->balanceService->debit($project, $feeAmount, 'available', "Advance Fee ({$feePercentage}%)", null);
        }

        return [
            'original_amount' => $amount,
            'fee_percentage' => $feePercentage,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
        ];
    }
}
