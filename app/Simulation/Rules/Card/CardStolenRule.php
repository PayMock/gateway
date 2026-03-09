<?php

namespace App\Simulation\Rules\Card;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule CARD_STOLEN: card ending in 0000 → stolen card → fraud.
 */
final class CardStolenRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        $fraudChecks = ['0000', '1313', '171', '13'];

        foreach ($fraudChecks as $value) {
            if ($this->cardEndsWith($context, strval($value))) {
                return true;
            }
        }

        return $this->cardEndsWith($context, '0000');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::fraud('stolen_card', $this->identifier());
    }

    public function priority(): int
    {
        return 100;
    }

    public function identifier(): string
    {
        return 'CARD_STOLEN';
    }
}
