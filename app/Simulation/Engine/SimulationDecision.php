<?php

namespace App\Simulation\Engine;

/**
 * Immutable result of the simulation engine rule evaluation.
 */
final class SimulationDecision
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $reason,
        public readonly ?string $rule,
        public readonly int $delayMs = 0,
        public readonly array $sideEffects = [],
    ) {
    }

    public static function approved(?string $rule = null, int $delayMs = 0): self
    {
        return new self(
            status: 'approved',
            reason: null,
            rule: $rule,
            delayMs: $delayMs,
        );
    }

    public static function failed(string $reason, string $rule, int $delayMs = 0): self
    {
        return new self(
            status: 'failed',
            reason: $reason,
            rule: $rule,
            delayMs: $delayMs,
        );
    }

    public static function fraud(string $reason, string $rule): self
    {
        return new self(
            status: 'fraud',
            reason: $reason,
            rule: $rule,
        );
    }

    public static function processing(string $reason, string $rule, int $delayMs = 0): self
    {
        return new self(
            status: 'processing',
            reason: $reason,
            rule: $rule,
            delayMs: $delayMs,
        );
    }

    public static function pending(string $reason, string $rule, int $delayMs = 0): self
    {
        return new self(
            status: 'pending',
            reason: $reason,
            rule: $rule,
            delayMs: $delayMs,
        );
    }

    public function withSideEffect(string $effect): self
    {
        return new self(
            status: $this->status,
            reason: $this->reason,
            rule: $this->rule,
            delayMs: $this->delayMs,
            sideEffects: array_merge($this->sideEffects, [$effect]),
        );
    }

    public function hasSideEffect(string $effect): bool
    {
        return in_array($effect, $this->sideEffects, strict: true);
    }
}
