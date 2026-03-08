<?php

namespace App\Simulation\Pipeline;

use App\Simulation\Rules\RuleInterface;
use Illuminate\Support\Collection;

/**
 * Registry of all available simulation rules.
 * Rules are instantiated from the simulation_rules config.
 */
final class RuleRegistry
{
    /** @var Collection<int, RuleInterface> */
    private Collection $rules;

    public function __construct()
    {
        $this->rules = $this->loadRules();
    }

    /** @return Collection<int, RuleInterface> */
    public function all(): Collection
    {
        return $this->rules;
    }

    public function findByIdentifier(string $identifier): ?RuleInterface
    {
        return $this->rules->first(
            fn (RuleInterface $rule) => $rule->identifier() === $identifier
        );
    }

    /** @return Collection<int, RuleInterface> */
    private function loadRules(): Collection
    {
        $ruleClasses = config('simulation_rules.rules', []);

        return collect($ruleClasses)
            ->map(fn (string $class) => app($class))
            ->sortByDesc(fn (RuleInterface $rule) => $rule->priority())
            ->values();
    }
}
