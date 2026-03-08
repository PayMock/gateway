<?php

namespace App\Simulation\Rules;

use App\Simulation\Engine\SimulationContext;

/**
 * Base class with shared utilities. All concrete rules extend this.
 */
abstract class AbstractRule implements RuleInterface
{
    protected function amountContains(SimulationContext $context, string $fragment): bool
    {
        return str_contains($context->amountString(), $fragment);
    }

    protected function amountEndsWith(SimulationContext $context, string $suffix): bool
    {
        return str_ends_with($context->amountString(), $suffix);
    }

    protected function cardEndsWith(SimulationContext $context, string $suffix): bool
    {
        if ($context->cardNumber === null) {
            return false;
        }

        return str_ends_with($context->cardNumber, $suffix);
    }

    protected function matchesAmount(SimulationContext $context, float $amount): bool
    {
        return abs($context->amount - $amount) < 0.001;
    }

    protected function isPix(SimulationContext $context): bool
    {
        return $context->method === 'pix';
    }

    protected function isCreditCard(SimulationContext $context): bool
    {
        return $context->method === 'credit_card';
    }
}
