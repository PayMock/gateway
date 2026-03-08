<?php

namespace App\Simulation\Rules\Amount;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule AMOUNT_ZERO: amount is 0 → invalid transaction.
 */
final class ZeroAmountRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $context->amount <= 0;
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::failed('invalid_amount', $this->identifier());
    }

    public function priority(): int
    {
        return 110;
    }

    public function identifier(): string
    {
        return 'AMOUNT_ZERO';
    }
}
