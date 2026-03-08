<?php

namespace App\Simulation\Engine;

/**
 * Immutable data transfer object holding all context required
 * by the simulation engine to evaluate rules.
 */
final class SimulationContext
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $method,
        public readonly ?string $cardNumber,
        public readonly ?string $customerName,
        public readonly ?string $customerEmail,
        public readonly \DateTimeImmutable $timestamp,
        public readonly ?string $forcedRule = null,
    ) {
    }

    public function cardLastFour(): ?string
    {
        if ($this->cardNumber === null) {
            return null;
        }

        return substr($this->cardNumber, -4);
    }

    public function amountString(): string
    {
        return number_format($this->amount, 2, '.', '');
    }

    public function getAmountCents(): int
    {
        return (int) round($this->amount * 100);
    }
}
