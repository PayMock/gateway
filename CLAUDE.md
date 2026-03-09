# PayMock Gateway — CLAUDE.md

This file provides context for Claude, helping it understand the project structure and coding conventions.

## What is PayMock?

An open-source **simulated payment gateway** for developer testing and integration. It mimics the behavior of real gateways (Stripe, MercadoPago) including latency, antifraude, webhooks, and realistic error scenarios.

## Tech Stack

| Layer | Technology |
|---|---|
| API & Business Logic | Laravel 11 / PHP 8.3 |
| Async Workers | AmpPHP |
| Database | PostgreSQL 16 |
| Cache/Queues | Redis 7 (Streams) |
| Documentation | Dedoc/Scramble (OpenAPI) |

## Coding Standards

**Must follow** `UNIVERSAL-CODE-STYLE-RULES.md` in current or in the parent directory.

Key points:
- **Explicit braces** on all control structures (never `if (x) return;`)
- **Early returns / guard clauses** — avoid nested ifs
- **Blank lines** between logical sections
- **Named constructors** on DTOs (e.g., `SimulationDecision::fraud(...)`)
- **Final classes** where there's no need for extension
- All code in **English**

## Key Patterns

### Adding a new simulation rule

1. Create class in `app/Simulation/Rules/{Category}/YourRule.php`
2. Extend `AbstractRule`, implement `RuleInterface`
3. Implement `matches()`, `decide()`, `priority()`, `identifier()`
4. Register in `config/simulation_rules.php` → `rules` array

### Adding an API endpoint

1. Create method in existing controller (or new controller in `app/Http/Controllers/Api/`)
2. Add route to `routes/api.php` inside the appropriate auth group
3. Add docblock for Scramble OpenAPI generation
4. Write Feature test in `tests/Feature/Api/`

## Important Files

| File | Purpose |
|---|---|
| `config/gateway.php` | ID prefixes, statuses, retry config |
| `config/simulation_rules.php` | All rule classes + magic trigger values |
| `app/Simulation/Engine/SimulationContext.php` | DTO passed to all rules |
| `app/Simulation/Engine/SimulationDecision.php` | Result from simulation pipeline |
| `app/Services/Payments/PaymentService.php` | Orchestrates full payment flow |
| `amp/workers/webhook_worker.php` | Async webhook delivery with retries |
