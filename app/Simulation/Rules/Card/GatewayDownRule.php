<?php

namespace App\Simulation\Rules\Card;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule CARD_GATEWAY_DOWN: card ending in 9999 → gateway unavailable → failed (503-like).
 */
final class GatewayDownRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        return $this->cardEndsWith($context, '9999');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::failed('gateway_unavailable', $this->identifier());
    }

    public function priority(): int
    {
        return 95;
    }

    public function identifier(): string
    {
        return 'CARD_GATEWAY_DOWN';
    }
}
