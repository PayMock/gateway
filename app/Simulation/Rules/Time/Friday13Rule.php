<?php

namespace App\Simulation\Rules\Time;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule TIME_FRIDAY_13: on Friday the 13th all transactions go to manual review (pending).
 */
final class Friday13Rule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        $isFriday = (int) $context->timestamp->format('N') === 5;
        $isThirteenth = (int) $context->timestamp->format('d') === 13;

        return $isFriday && $isThirteenth;
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::pending('manual_review_required', $this->identifier());
    }

    public function priority(): int
    {
        return 68;
    }

    public function identifier(): string
    {
        return 'TIME_FRIDAY_13';
    }
}
