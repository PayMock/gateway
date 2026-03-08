<?php

namespace App\Simulation\Rules\Amount;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule FRAUD_013: amount contains digit sequence "13" → fraud.
 *
 * Example: 13.00, 100.13, 130.00, 1300.00 → fraud
 */
final class FraudAmountRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->amountContains($context, '13');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::fraud('fraud_suspected', $this->identifier());
    }

    public function priority(): int
    {
        return 100;
    }

    public function identifier(): string
    {
        return 'FRAUD_013';
    }
}
