<?php

namespace App\Simulation\Rules\Amount;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule TIMEOUT_999: amount exactly 999 → issuer timeout simulation.
 * Simulates the gateway waiting on the issuer and timing out.
 */
final class Timeout999Rule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->matchesAmount($context, 999.00);
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        // Simulate 6 second delay (timeout threshold is typically 5s)
        return SimulationDecision::failed('issuer_timeout', $this->identifier(), delayMs: 6000);
    }

    public function priority(): int
    {
        return 80;
    }

    public function identifier(): string
    {
        return 'TIMEOUT_999';
    }
}
