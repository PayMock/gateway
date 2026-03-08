<?php

namespace App\Simulation\Rules\Card;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule CARD_INVALID_CVV: card ending in 1234 → invalid CVV → failed.
 */
final class InvalidCvvRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->cardEndsWith($context, '1234');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::failed('invalid_cvv', $this->identifier());
    }

    public function priority(): int
    {
        return 90;
    }

    public function identifier(): string
    {
        return 'CARD_INVALID_CVV';
    }
}
