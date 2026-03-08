<?php

namespace App\Simulation\Pipeline;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;

/**
 * Executes the simulation rules in priority order.
 * Returns the decision from the first matching rule.
 * Falls back to a default "approved" if no rule matches.
 */
final class RulePipeline
{
    public function __construct(
        private readonly RuleRegistry $registry,
    ) {
    }

    public function run(SimulationContext $context): SimulationDecision
    {
        // X-PayMock-Rule forced simulation support
        if ($context->forcedRule !== null) {
            return $this->runForcedRule($context);
        }

        return $this->runPipeline($context);
    }

    private function runForcedRule(SimulationContext $context): SimulationDecision
    {
        $rule = $this->registry->findByIdentifier($context->forcedRule);

        if ($rule === null) {
            // Unknown forced rule → fall through to normal pipeline
            return $this->runPipeline($context);
        }

        return $rule->decide($context);
    }

    private function runPipeline(SimulationContext $context): SimulationDecision
    {
        foreach ($this->registry->all() as $rule) {
            if (! $rule->matches($context)) {
                continue;
            }

            return $rule->decide($context);
        }

        // Default: no rule matched → approved
        return SimulationDecision::approved();
    }
}
