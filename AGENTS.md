# PayMock Gateway — AGENTS.md

Context file for all AI coding agents working on this project.

## Project Purpose

Simulated payment gateway for developer testing. Realistic behavior — latency, fraud, webhooks, idempotency — without real money movement.

## Non-Negotiable Rules

1. **Language**: All code, comments, variable names, function names, table names in **English**
2. **Code style**: Follow `UNIVERSAL-CODE-STYLE-RULES.md` strictly
3. **Braces required**: No braceless control structures
4. **Early returns**: Use guard clauses, avoid else when possible
5. **No deep nesting**: Max 2 levels; use early return to flatten
6. **Final classes** where inheritance is not needed
7. **Explicit types**: Use PHP 8.x typed properties and return types
8. **API style**: Follow Stripe conventions (IDs, response format, errors, pagination)

## Architecture Boundaries

- **Laravel** handles all HTTP, authentication, business logic, DB writes
- **AmpPHP workers** handle high-throughput async tasks only (webhook delivery, queue consumers)
- **Eloquent** is fine for Laravel layer; AMP workers should use raw SQL for performance
- Do **not** use Eloquent inside AMP workers

## Simulation Engine Rules

When adding or modifying rules:
- Every rule must have a corresponding unit test in `SimulationRulesTest.php`
- Rules must NOT have side effects other than returning `SimulationDecision`
- Side effects (like duplicate webhook) → use `$decision->withSideEffect('effect_name')`
- Priority scale: fraud=100+, card=80-99, time=60-79, user=55-70, amount=50-80, pix=40-50, default=10

## API Contract

### Private (server-to-server) routes — `/api/v1/*`
- Authentication: `Authorization: Bearer sk_test_xxx`
- Special: `Idempotency-Key` and `X-PayMock-Rule` headers
- Responses: always include `id`, `object`, `created` fields
- Errors: `{"error": {"type": "...", "code": "...", "message": "..."}}`
- Lists: `{"object": "list", "data": [...], "has_more": bool}`

### Public (client-side) routes — `/api/v1/public/*`
- Authentication: `X-Public-Key: pk_test_xxx` (safe for browser/mobile)
- Origin control: `Origin` header validated against `project.allowed_origins`
  - If `allowed_origins` is null/empty → no origin restriction
  - Supports wildcard patterns: `*.domain.com`, `*.*.domain.com`
- Available endpoints:
  - `GET /api/v1/public/payment-methods` — list supported payment methods
  - `POST /api/v1/public/payments` — create payment
  - `GET /api/v1/public/payments/{id}/status` — poll payment status
  - `GET /api/v1/public/payments/{id}/qrcode` — fetch QR code SVG

## Database

- All PKs are UUIDs
- Use `public_id` (opaque string like `pay_xxx`) for the external API
- Use internal UUID `id` for DB foreign keys only
- Never expose internal UUIDs in API responses

## Environment Variables

| Variable | Description |
|---|---|
| `GATEWAY_QR_EXPIRY_MINUTES` | QR code expiry (default: 30) |
| `GATEWAY_WEBHOOK_RETRY_ATTEMPTS` | Max retry attempts (default: 4) |

## Testing

Run all tests: `php artisan test`

Required test coverage:
- Each simulation rule: `tests/Unit/Simulation/`
- Security services: `tests/Unit/Security/`
- Each API endpoint: `tests/Feature/Api/`
- All tests must use `RefreshDatabase`
- Mock Redis in tests: `Redis::shouldReceive('xadd')->andReturn('ok')`
