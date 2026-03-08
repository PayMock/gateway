<?php

namespace App\Services\Balances;

use App\Models\Balance;
use App\Models\BalanceTransaction;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class BalanceService
{
    /**
     * Credits an amount to a project's balance.
     */
    public function credit(
        Project $project,
        float $amount,
        string $balanceType = 'pending',
        ?string $description = null,
        ?Model $source = null
    ): BalanceTransaction {
        return DB::transaction(function () use ($project, $amount, $balanceType, $description, $source) {
            $balance = $this->getOrCreateBalance($project);

            // 1. Create Ledger Entry
            /** @var BalanceTransaction $transaction */
            $transaction = BalanceTransaction::create([
                'project_id' => $project->id,
                'type' => 'credit',
                'balance_type' => $balanceType,
                'amount' => $amount,
                'description' => $description,
                'source_type' => $source ? $source->getMorphClass() : null,
                'source_id' => $source ? $source->getKey() : null,
            ]);

            // 2. Update Balance Table
            $balance->increment($balanceType, $amount);

            return $transaction;
        });
    }

    /**
     * Debits an amount from a project's balance.
     */
    public function debit(
        Project $project,
        float $amount,
        string $balanceType = 'available',
        ?string $description = null,
        ?Model $source = null
    ): BalanceTransaction {
        return DB::transaction(function () use ($project, $amount, $balanceType, $description, $source) {
            $balance = $this->getOrCreateBalance($project);

            // 1. Create Ledger Entry
            /** @var BalanceTransaction $transaction */
            $transaction = BalanceTransaction::create([
                'project_id' => $project->id,
                'type' => 'debit',
                'balance_type' => $balanceType,
                'amount' => $amount,
                'description' => $description,
                'source_type' => $source ? $source->getMorphClass() : null,
                'source_id' => $source ? $source->getKey() : null,
            ]);

            // 2. Update Balance Table
            $balance->decrement($balanceType, $amount);

            return $transaction;
        });
    }

    /**
     * Moves funds from pending to available (Settlement).
     */
    public function settle(Project $project, float $amount, ?string $description = null, ?Model $source = null): void
    {
        DB::transaction(function () use ($project, $amount, $description, $source) {
            // Debit from Pending
            $this->debit($project, $amount, 'pending', "Settlement: {$description} (Pending Out)", $source);

            // Credit to Available
            $this->credit($project, $amount, 'available', "Settlement: {$description} (Available In)", $source);
        });
    }

    public function getOrCreateBalance(Project $project): Balance
    {
        return Balance::firstOrCreate(
            ['project_id' => $project->id],
            ['available' => 0, 'pending' => 0, 'withdrawn' => 0]
        );
    }
}
