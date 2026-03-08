<?php

namespace App\Simulation\Rules\Pix;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule PIX_DUPLICATE_WEBHOOK: PIX amount ends in .77 → approved but webhook is sent twice.
 * Forces clients to handle idempotency.
 */
final class PixDuplicateWebhookRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        if (! $this->isPix($context)) {
            return false;
        }

        return $this->amountEndsWith($context, '.77');
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        $decision = SimulationDecision::approved($this->identifier());

        return $decision->withSideEffect('duplicate_webhook');
    }

    public function priority(): int
    {
        return 42;
    }

    public function identifier(): string
    {
        return 'PIX_DUPLICATE_WEBHOOK';
    }
}
