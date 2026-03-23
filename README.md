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
- ✅ **Charge model** — separate payment intent (merchant) from execution (customer)
- ✅ **Public API** (`/v1/public/*`) — client-side routes with `public_key` + origin allowlist
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
  "api_key": "sk_test_PROJECT_API_KEY",
  "public_key": "pk_test_PUBLIC_KEY",
  "allowed_origins": []
}
```

The `api_key` is for **server-to-server** calls only. The `public_key` is safe to embed in frontend code.

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

### 6. Charge-based payment flow (frontend integration)

This is the recommended flow when your frontend needs to accept payments.

**Step 1 — Backend creates a charge:**

```bash
curl -X POST http://localhost:8080/api/v1/charges \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{"amount": 50.00, "description": "Order #2049", "customer_email": "jane@example.com"}'
# → { "id": "chg_xxx", "status": "pending" }
```

**Step 2 — Frontend pays the charge (PIX):**

```bash
curl -X POST http://localhost:8080/api/v1/public/charges/chg_xxx/pay \
  -H "X-Public-Key: pk_test_xxx" \
  -H "Origin: https://myapp.com" \
  -H "Content-Type: application/json" \
  -d '{"method": "pix"}'
```

Response:
```json
{
  "status": "pending",
  "method": "pix",
  "payment": {
    "qr_code_url": "http://localhost:8080/pay/TOKEN",
    "qr_code_base64": "PHN2Zy4uLg==",
    "qr_code_mime": "image/svg+xml"
  }
}
```

Embed the QR code inline: `<img src="data:image/svg+xml;base64,{qr_code_base64}" />`

The customer visits `qr_code_url`, simulates payment by clicking **"Confirm Payment"**, and the charge becomes `paid`.

**Step 2 (alternative) — Frontend pays the charge (credit card):**

```bash
curl -X POST http://localhost:8080/api/v1/public/charges/chg_xxx/pay \
  -H "X-Public-Key: pk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{"method": "credit_card", "card_number": "4111111111111111",
       "card_holder_name": "Jane Doe", "card_expiry": "12/28", "card_cvv": "123"}'
# → status "paid" (charge approved) or "fraud"/"failed" based on simulation rules
```

**Step 3 — Poll charge status:**

```bash
curl http://localhost:8080/api/v1/public/charges/chg_xxx/status \
  -H "X-Public-Key: pk_test_xxx"
# → { "status": "pending" | "paid" | "canceled" }
```

**Restricting origins (optional):**

```bash
curl -X POST http://localhost:8080/api/v1/projects \
  -H "Content-Type: application/json" \
  -d '{"name": "My App", "allowed_origins": ["https://myapp.com", "*.staging.myapp.com"]}'
```

Wildcard rules:
- `*.domain.com` — one subdomain level (`app.domain.com` ✅, `x.app.domain.com` ❌)
- `*.*.domain.com` — two subdomain levels (`x.app.domain.com` ✅)

### 7. Payout (Withdrawal)

```bash
curl -X POST http://localhost:8080/api/v1/payouts \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100.00,
    "transfer_details": {
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

#### Private routes (server-to-server, `Authorization: Bearer sk_test_xxx`)

```
POST   /api/v1/projects              Create project (no auth)
GET    /api/v1/projects/me           Get current project

GET    /api/v1/charges               List charges
POST   /api/v1/charges               Create charge (chg_xxx)
GET    /api/v1/charges/{id}          Get charge
POST   /api/v1/charges/{id}/cancel   Cancel charge

POST   /api/v1/payments              Direct payment (server-side)
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

#### Public routes (client-side, `X-Public-Key: pk_test_xxx` + `Origin`)

```
GET    /api/v1/public/payment-methods           List payment methods
POST   /api/v1/public/charges/{id}/pay          Pay charge (PIX or card)
GET    /api/v1/public/charges/{id}/status       Poll charge status
GET    /api/v1/public/charges/{id}/qrcode       PIX QR code SVG
```

#### Web (browser) — PIX confirmation simulation

```
GET    /pay/{token}                  Payment confirmation page
POST   /pay/{token}/confirm          Simulate customer confirming PIX
```

---

## Architecture

```
Merchant (backend)                   Customer (browser/mobile)
        │                                      │
        ▼                                      ▼
POST /api/v1/charges              POST /api/v1/public/charges/{id}/pay
Authorization: Bearer sk_test_xxx  X-Public-Key: pk_test_xxx
        │                          Origin: https://myapp.com
        ▼                                      │
    ChargeService                              ▼
    creates Charge (chg_xxx)       PIX: create pending Transaction
        │                              → return QR code URL + base64
        │                          Card: ChargeService → PaymentService
        │                              → SimulationEngine → Decision
        │                              → if approved → charge.status = paid
        ▼
WebhookDispatcher → Redis Stream
                          │
                          ▼
                   AMP Webhook Worker
                   (concurrent HTTP delivery)

PIX confirmation (simulated):
  Customer visits /pay/{token} → clicks "Confirm" → transaction + charge = paid
```

---

## Running Tests

```bash
php artisan test
```

---

## License

MIT License — see [LICENSE](LICENSE)
