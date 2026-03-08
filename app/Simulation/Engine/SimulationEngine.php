<?php

namespace App\Simulation\Engine;

use App\Simulation\Pipeline\RulePipeline;

/**
 * Entry point for the simulation engine.
 * Orchestrates context creation, rule evaluation, and returns a decision.
 */
final class SimulationEngine
{
    public function __construct(
        private readonly RulePipeline $pipeline,
    ) {
    }

    public function process(SimulationContext $context): SimulationDecision
    {
        return $this->pipeline->run($context);
    }
}
