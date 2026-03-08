<?php

namespace App\Simulation\Rules\Amount;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule LUCKY_777: amount exactly 777 → always approved immediately.
 */
final class Lucky777Rule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->matchesAmount($context, 777.00);
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::approved($this->identifier());
    }

    public function priority(): int
    {
        return 50;
    }

    public function identifier(): string
    {
        return 'LUCKY_777';
    }
}
