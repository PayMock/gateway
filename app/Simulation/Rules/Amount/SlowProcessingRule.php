<?php

namespace App\Simulation\Rules\Amount;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule SLOW_PROCESSING: amount exactly 1.23 → enters processing state with 4s delay.
 * Simulates slow gateway processing.
 */
final class SlowProcessingRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->matchesAmount($context, 1.23);
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::processing('slow_gateway', $this->identifier(), delayMs: 4000);
    }

    public function priority(): int
    {
        return 55;
    }

    public function identifier(): string
    {
        return 'SLOW_PROCESSING';
    }
}
