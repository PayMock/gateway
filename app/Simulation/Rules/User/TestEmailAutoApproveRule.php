<?php

namespace App\Simulation\Rules\User;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule USER_TEST_EMAIL: customer email containing "test" → auto approved.
 * Useful for automated test suites that need deterministic approvals.
 */
final class TestEmailAutoApproveRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        if ($context->customerEmail === null) {
            return false;
        }

        return str_contains(strtolower($context->customerEmail), 'test');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::approved($this->identifier());
    }

    public function priority(): int
    {
        return 58;
    }

    public function identifier(): string
    {
        return 'USER_TEST_EMAIL';
    }
}
