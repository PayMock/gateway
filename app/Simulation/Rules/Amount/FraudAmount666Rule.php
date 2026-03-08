<?php

namespace App\Simulation\Rules\Amount;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule FRAUD_666: amount exactly 666 → fraud (devil's number).
 */
final class FraudAmount666Rule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->matchesAmount($context, 666.00);
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::fraud('fraud_suspected', $this->identifier());
    }

    public function priority(): int
    {
        return 98;
    }

    public function identifier(): string
    {
        return 'FRAUD_666';
    }
}
