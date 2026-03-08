<?php

namespace App\Simulation\Rules\Time;

use App\Simulation\Engine\SimulationContext;
use App\Simulation\Engine\SimulationDecision;
use App\Simulation\Rules\AbstractRule;

/**
 * Rule TIME_MAINTENANCE: between 00:00 and 00:05 the gateway is under maintenance.
 */
final class MaintenanceWindowRule extends AbstractRule
{
    public function matches(SimulationContext $context): bool
    {
        $hour = (int) $context->timestamp->format('H');
        $minute = (int) $context->timestamp->format('i');

        $isAfterMidnight = $hour === 0;
        $isWithinFiveMinutes = $minute <= 5;

        return $isAfterMidnight && $isWithinFiveMinutes;
    }

    public function decide(SimulationContext $context): SimulationDecision
    {
        return SimulationDecision::failed('gateway_maintenance', $this->identifier());
    }

    public function priority(): int
    {
        return 70;
    }

    public function identifier(): string
    {
        return 'TIME_MAINTENANCE';
    }
}
