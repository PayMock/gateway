<?php

namespace App\Simulation\Rules\Pix;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule PIX_APPROVED: PIX payment where amount ends in .00 → always approved.
 * Example: 50.00, 200.00 → approved
 */
final class PixApprovedRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        if (! $this->isPix($context)) {
            return false;
        }

        return $this->amountEndsWith($context, '.00');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::approved($this->identifier());
    }

    public function priority(): int
    {
        return 40;
    }

    public function identifier(): string
    {
        return 'PIX_APPROVED_00';
    }
}
