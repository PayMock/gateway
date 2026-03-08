<?php

namespace App\Simulation\Rules\Pix;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule PIX_FRAUD: PIX payment where amount ends in .13 → fraud.
 * Example: 50.13, 200.13 → fraud
 */
final class PixFraudRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        if (! $this->isPix($context)) {
            return false;
        }

        return $this->amountEndsWith($context, '.13');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::fraud('pix_fraud_pattern', $this->identifier());
    }

    public function priority(): int
    {
        return 100;
    }

    public function identifier(): string
    {
        return 'PIX_FRAUD_013';
    }
}
