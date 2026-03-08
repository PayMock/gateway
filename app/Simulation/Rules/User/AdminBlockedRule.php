<?php

namespace App\Simulation\Rules\User;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule USER_ADMIN_BLOCKED: customer with name "admin" → blocked.
 */
final class AdminBlockedRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        if ($context->customerName === null) {
            return false;
        }

        return strtolower($context->customerName) === 'admin';
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::failed('customer_blocked', $this->identifier());
    }

    public function priority(): int
    {
        return 60;
    }

    public function identifier(): string
    {
        return 'USER_ADMIN_BLOCKED';
    }
}
