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

### Adding a public (client-side) endpoint

1. Add method to `PublicChargeController` (or create a dedicated public controller)
2. Add route under the `v1/public` prefix with `AuthenticatePublicRequest` middleware
3. Public endpoints must NOT expose internal fields (e.g. `simulation_rule`, `api_key`)

### Charge flow

The charge concept separates "create billing intent" (merchant) from "execute payment" (customer):
1. `POST /api/v1/charges` (private) — merchant creates a charge (`chg_xxx`)
2. `POST /api/v1/public/charges/{id}/pay` (public) — customer pays:
   - `method: pix` → creates pending transaction, returns QR code URL + base64
   - `method: credit_card` → runs simulation engine, returns final status
3. PIX confirmation happens at `GET/POST /pay/{token}` (web page with "Confirm" button)

## Important Files

| File | Purpose |
|---|---|
| `config/gateway.php` | ID prefixes, statuses, retry config |
| `config/simulation_rules.php` | All rule classes + magic trigger values |
| `app/Simulation/Engine/SimulationContext.php` | DTO passed to all rules |
| `app/Simulation/Engine/SimulationDecision.php` | Result from simulation pipeline |
| `app/Services/Payments/PaymentService.php` | Orchestrates full payment flow (direct API) |
| `app/Services/Charges/ChargeService.php` | Charge creation and payment orchestration |
| `app/Services/Payments/QrCodeService.php` | QR code generation (SVG, URL, base64) |
| `app/Services/Security/OriginValidator.php` | Origin wildcard matching for public routes |
| `app/Http/Middleware/AuthenticatePublicRequest.php` | Public key + origin authentication |
| `app/Http/Controllers/Api/ChargeController.php` | Private charge CRUD |
| `app/Http/Controllers/Api/PublicChargeController.php` | Public charge payment endpoints |
| `app/Http/Controllers/PaymentPageController.php` | Web QR confirmation page (/pay/{token}) |
| `amp/workers/webhook_worker.php` | Async webhook delivery with retries |
