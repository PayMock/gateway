# PayMock Gateway

> Open-source simulated payment gateway for developer testing — built to mimic Stripe and MercadoPago behavior.

[![Laravel](https://img.shields.io/badge/Laravel-11-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-blue.svg)](https://postgresql.org)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## What is PayMock?

PayMock is a realistic payment gateway simulator. It's designed for:

- **Integration testing** — test your payment flows without real money
- **Development** — build payment features with a local gateway
- **Education** — learn how payment gateways work internally

## Features

- ✅ Stripe-style REST API (`/v1/payments`, opaque IDs, cursor pagination)
- ✅ Multiple payment methods: `credit_card`, `pix`, `qrcode`, `internal_balance`
- ✅ 17 built-in simulation rules (fraud, timeout, maintenance window, and more)
- ✅ Forced simulation via `X-PayMock-Rule` header
- ✅ Idempotency via `Idempotency-Key` header
- ✅ QR code generation for PIX payments
- ✅ Webhook delivery with exponential retry (via AmpPHP)
- ✅ Duplicate webhook simulation (idempotency testing)
- ✅ **Balance & Settlement system** (Pending, Available, Withdrawn)
- ✅ **Cash Advance (Anticipation)** with configurable fee schedules
- ✅ **Payout Management** for withdrawals
- ✅ Automatic OpenAPI docs via Scramble (`/docs/api`)

---

## Quick Start

### With Docker (recommended)

```bash
git clone https://github.com/yourhandle/paymock-gateway.git
cd paymock-gateway

cp .env.example .env
docker compose up -d
docker compose exec app php artisan migrate
```

API will be available at `http://localhost:8080`

### Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

---

## Usage

### 1. Create a project

```bash
curl -X POST http://localhost:8080/api/v1/projects \
  -H "Content-Type: application/json" \
  -d '{"name": "My Test App"}'
```

Response:
```json
{
  "id": "proj_xxxxxxxxxxx",
  "object": "project",
  "api_key": "sk_test_PROJECT_API_KEY"
}
```

### 2. Create a payment

```bash
curl -X POST http://localhost:8080/api/v1/payments \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{"amount": 100.00, "currency": "BRL", "method": "credit_card"}'
```

### 3. Force a specific simulation outcome

```bash
curl -X POST http://localhost:8080/api/v1/payments \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "X-PayMock-Rule: FRAUD_013" \
  -H "Content-Type: application/json" \
  -d '{"amount": 50.00, "method": "credit_card"}'
# → status: "fraud"
```

### 4. Check balance

```bash
curl -X GET http://localhost:8080/api/v1/balance \
  -H "Authorization: Bearer sk_test_xxx"
```

Example response:
```json
{
  "project_id": "proj_xxxxx",
  "available": 100.00,
  "pending": 500.00,
  "withdrawn": 0.00,
  "currency": "BRL"
}
```

### 5. Request cash advance (Anticipation)

```bash
# 1. See options
curl -X GET http://localhost:8080/api/v1/balance/advance/options \
  -H "Authorization: Bearer sk_test_xxx"

# 2. Request instant release (10% fee)
curl -X POST http://localhost:8080/api/v1/balance/advance \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{"timeframe": "instant"}'
```

### 6. Payout (Withdrawal)

```bash
curl -X POST http://localhost:8080/api/v1/payouts \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100.00,
    "bank_details": {
      "pix_key": "user@example.com",
      "bank_name": "Mock Bank"
    }
  }'
```

---

## Simulation Rules

| Rule ID | Trigger | Outcome |
|---|---|---|
| `FRAUD_013` | Amount contains "13" | `fraud` |
| `FRAUD_666` | Amount = 666 | `fraud` |
| `LUCKY_777` | Amount = 777 | `approved` |
| `AMOUNT_ZERO` | Amount ≤ 0 | `failed` — invalid_amount |
| `TIMEOUT_999` | Amount = 999 | `failed` — issuer_timeout + 6s delay |
| `SLOW_PROCESSING` | Amount = 1.23 | `processing` + 4s delay |
| `CARD_STOLEN` | Card ends 0000 | `fraud` |
| `CARD_INVALID_CVV` | Card ends 1234 | `failed` — invalid_cvv |
| `CARD_ISSUER_UNAVAILABLE` | Card ends 8888 | `failed` — issuer_unavailable |
| `CARD_GATEWAY_DOWN` | Card ends 9999 | `failed` — gateway_unavailable |
| `PIX_FRAUD_013` | PIX, amount ends .13 | `fraud` |
| `PIX_APPROVED_00` | PIX, amount ends .00 | `approved` |
| `PIX_DUPLICATE_WEBHOOK` | PIX, amount ends .77 | `approved` + duplicate webhook |
| `TIME_MAINTENANCE` | 00:00 – 00:05 UTC | `failed` — gateway_maintenance |
| `TIME_FRIDAY_13` | Friday the 13th | `pending` — manual_review |
| `USER_ADMIN_BLOCKED` | customer_name = "admin" | `failed` — customer_blocked |
| `USER_TEST_EMAIL` | Email contains "test" | `approved` |

See all rules: `GET /api/v1/simulation/rules`

---

## API Reference

Interactive documentation (Swagger UI): `http://localhost:8080/docs/api`

OpenAPI JSON: `http://localhost:8080/docs/api.json`

### Endpoints

```
POST   /api/v1/projects              Create project (public)
GET    /api/v1/projects/me           Get current project

POST   /api/v1/payments              Create payment
GET    /api/v1/payments              List payments
GET    /api/v1/payments/{id}         Get payment
POST   /api/v1/payments/{id}/cancel  Cancel payment

GET    /api/v1/balance               Account balance summary
GET    /api/v1/balance/history       Ledger transactions
GET    /api/v1/balance/advance/opts  List advance fees
POST   /api/v1/balance/advance       Request anticipation

GET    /api/v1/payouts               List payouts
POST   /api/v1/payouts               Request withdrawal
GET    /api/v1/payouts/{id}          Get payout details

POST   /api/v1/webhooks              Register webhook
GET    /api/v1/webhooks              List webhooks

GET    /api/v1/simulation/rules      List simulation rules
POST   /api/v1/simulate/payment      Force simulation scenario
```

---

## Architecture

```
Client
  │
  ▼
Laravel API (/api/v1/*)
  │
  ▼
PaymentService → SimulationEngine → SimulationDecision
  │
  ▼
WebhookDispatcher → Redis Stream
                          │
                          ▼
                   AMP Webhook Worker
                   (concurrent HTTP delivery)
```

---

## Running Tests

```bash
php artisan test
```

---

## License

MIT License — see [LICENSE](LICENSE)
