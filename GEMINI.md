# PayMock Gateway — GEMINI.md

## Project Overview

PayMock Gateway is an open-source simulated payment gateway built with:
- **Laravel 11** (PHP 8.3) — main API and business logic
- **AmpPHP** — async/concurrent webhook workers
- **PostgreSQL 16** — primary database
- **Redis 7** — queue (Redis Streams) and cache
- **Docker Compose** — development environment

The goal is to realistically simulate Stripe/MercadoPago-style payment flows for developer testing.

---

## Architecture

```
Client (API consumer)
    │
    ▼
Laravel API (/v1/*)
    │   Authentication: Authorization: Bearer sk_test_xxx
    │   Special: Idempotency-Key, X-PayMock-Rule headers
    ▼
PaymentService
    ├── SimulationEngine → evaluates RulePipeline → SimulationDecision
    ├── QrCodeService → generates signed QR tokens
    └── WebhookDispatcher → pushes to Redis Stream
                                │
                                ▼
                        AMP Webhook Worker
                        (concurrent HTTP delivery)
```

---

## Key Design Decisions

1. **Stripe-style API**: `/v1/payments`, opaque IDs (`pay_xxx`, `sk_test_xxx`), cursor pagination, `object` field in responses
2. **Simulation Engine**: Rule pipeline with priority ordering. Higher priority = evaluated first. First matching rule wins.
3. **No auth on project creation**: POST /v1/projects is public to allow bootstrapping
4. **Idempotency**: Supported via `Idempotency-Key` header, checked before simulation
5. **Forced rules**: `X-PayMock-Rule: RULE_ID` bypasses the normal pipeline for deterministic testing
6. **Dual key system**: `api_key` (sk_test_xxx) for server-to-server; `public_key` (pk_test_xxx) for client-side
7. **Origin allowlist**: Public routes validate the `Origin` header against `project.allowed_origins`; wildcards supported

---

## Code Style Rules

See `UNIVERSAL-CODE-STYLE-RULES.md` in the parent directory. Key rules:
- Always use braces for all control structures
- Early returns / guard clauses over nested ifs
- Explicit over implicit
- No one-liners
- Block scope variables only
- Blank lines between logical sections

---

## Directory Structure

```
app/
  Http/Controllers/Api/      — API controllers
  Http/Middleware/           — AuthenticateProject, AuthenticatePublicRequest
  Models/                    — Eloquent models
  Services/
    Payments/                — PaymentService, QrCodeService
    Webhooks/                — WebhookDispatcher, WebhookPayloadBuilder
    Security/                — TokenGenerator, SignatureService, OriginValidator
  Simulation/
    Engine/                  — SimulationEngine, SimulationContext, SimulationDecision
    Rules/                   — Card/, Amount/, Pix/, Time/, User/ rule classes
    Pipeline/                — RuleRegistry, RulePipeline

amp/
  bootstrap.php              — Laravel bootstrap for AMP workers
  workers/                   — webhook_worker.php, payment_processor.php
  helpers/                   — AmpDelay, AmpDb, AmpRedis, AmpHttp

config/
  gateway.php                — prefixes, statuses, webhook retry config
  simulation_rules.php       — all rule classes and magic trigger values

database/
  migrations/                — 8 migrations (projects → balances)
```

---

## Simulation Rules Quick Reference

| Rule ID | Trigger | Result |
|---|---|---|
| FRAUD_013 | amount contains "13" | fraud |
| FRAUD_666 | amount = 666 | fraud |
| LUCKY_777 | amount = 777 | approved |
| AMOUNT_ZERO | amount ≤ 0 | failed (invalid_amount) |
| TIMEOUT_999 | amount = 999 | failed (issuer_timeout) + 6s delay |
| SLOW_PROCESSING | amount = 1.23 | processing + 4s delay |
| CARD_STOLEN | card ends 0000 | fraud |
| CARD_INVALID_CVV | card ends 1234 | failed |
| CARD_ISSUER_UNAVAILABLE | card ends 8888 | failed + 1.5s delay |
| CARD_GATEWAY_DOWN | card ends 9999 | failed (gateway_unavailable) |
| PIX_FRAUD_013 | pix + amount ends .13 | fraud |
| PIX_APPROVED_00 | pix + amount ends .00 | approved |
| PIX_DUPLICATE_WEBHOOK | pix + amount ends .77 | approved + duplicate_webhook |
| TIME_MAINTENANCE | 00:00–00:05 UTC | failed (gateway_maintenance) |
| TIME_FRIDAY_13 | Friday the 13th | pending (manual_review_required) |
| USER_ADMIN_BLOCKED | customer_name = "admin" | failed (customer_blocked) |
| USER_TEST_EMAIL | email contains "test" | approved |

---

## API Endpoints

```
POST   /api/v1/projects              — create project (public)
GET    /api/v1/projects/me           — get current project

POST   /api/v1/payments              — create payment
GET    /api/v1/payments              — list payments (?status=&limit=&starting_after=)
GET    /api/v1/payments/{id}         — get payment
POST   /api/v1/payments/{id}/cancel  — cancel payment

GET    /api/v1/balance               — get balance

POST   /api/v1/webhooks              — register webhook
GET    /api/v1/webhooks              — list webhooks

GET    /api/v1/simulation/rules      — list all simulation rules
POST   /api/v1/simulate/payment      — force a simulation scenario

--- Public (client-side) — X-Public-Key + Origin validation ---

GET    /api/v1/public/payment-methods           — list payment methods
POST   /api/v1/public/payments                  — create payment
GET    /api/v1/public/payments/{id}/status      — poll payment status
GET    /api/v1/public/payments/{id}/qrcode      — QR code SVG image
```

---

## Tests

Run: `php artisan test`

Files:
- `tests/Unit/Simulation/SimulationRulesTest.php` — all rule logic
- `tests/Unit/Security/OriginValidatorTest.php` — origin wildcard matching
- `tests/Feature/Api/ProjectApiTest.php` — project CRUD
- `tests/Feature/Api/PaymentApiTest.php` — payment lifecycle + simulation rules
- `tests/Feature/Api/PublicPaymentApiTest.php` — public routes + origin validation
