<?php

namespace App\Simulation\Rules;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;

/**
 * Contract that all simulation rules must implement.
 */
interface RuleInterface
{
    /**
     * Returns true when this rule should apply to the given context.
     */
    public function matches(SimulationContext $context): bool;

    /**
     * Returns the simulation decision for a matching context.
     * Only called if matches() returned true.
     */
    public function decide(SimulationContext $context): SimulationDecision;

    /**
     * Higher numbers are evaluated first.
     * fraud=100, validation=80, time=70, user=60, amount=50, pix=40, default=10
     */
    public function priority(): int;

    /**
     * Unique rule identifier, used in logs and X-PayMock-Rule header.
     * Example: FRAUD_013, CARD_STOLEN
     */
    public function identifier(): string;
}
