<?php

namespace App\Simulation\Rules\Card;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule CARD_ISSUER_UNAVAILABLE: card ending in 8888 → issuer down → failed.
 */
final class IssuerUnavailableRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->cardEndsWith($context, '8888');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        // Simulate issuer delay (500–2000ms)
        return SimulationDecision::failed('issuer_unavailable', $this->identifier(), delayMs: 1500);
    }

    public function priority(): int
    {
        return 88;
    }

    public function identifier(): string
    {
        return 'CARD_ISSUER_UNAVAILABLE';
    }
}
