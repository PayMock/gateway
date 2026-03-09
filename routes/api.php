<?php

use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\ChargeController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PublicChargeController;
use App\Http\Controllers\Api\PublicPaymentController;
use App\Http\Controllers\Api\SimulationController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\AuthenticateProject;
use App\Http\Middleware\AuthenticatePublicRequest;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PayMock API Routes — v1
|--------------------------------------------------------------------------
|
| Private routes (server-to-server):
|   Authorization: Bearer sk_test_xxx
|   Idempotency-Key: <string>   — prevents duplicate payments
|   X-PayMock-Rule: <rule_id>  — forces a specific simulation outcome
|
| Public routes (client-side):
|   X-Public-Key: pk_test_xxx  — safe for browser/mobile
|   Origin: https://yourdomain.com  — validated against project's allowed_origins
|
| Charge flow:
|   1. Merchant creates charge: POST /v1/charges (private)
|   2. Customer pays charge:    POST /v1/public/charges/{id}/pay (public)
|        PIX → returns qr_code_url + qr_code_base64 (pending until confirmed)
|        Card → simulation runs immediately, returns final status
|
*/

Route::prefix('v1')->group(function () {
    // Unauthenticated — project creation (bootstrap)
    Route::post('projects', [ProjectController::class, 'store']);

    // Public client-side routes — authenticated via X-Public-Key + Origin check
    Route::prefix('public')->middleware(AuthenticatePublicRequest::class)->group(function () {
        // Utility
        Route::get('payment-methods', [PublicPaymentController::class, 'paymentMethods']);

        // Charges — pay an existing charge, poll status, get QR code
        Route::post('charges/{id}/pay', [PublicChargeController::class, 'pay']);
        Route::get('charges/{id}/status', [PublicChargeController::class, 'status']);
        Route::get('charges/{id}/qrcode', [PublicChargeController::class, 'qrCode']);
    });

    // Private server-to-server routes — authenticated via Bearer sk_test_xxx
    Route::middleware(AuthenticateProject::class)->group(function () {
        // Project
        Route::get('projects/me', [ProjectController::class, 'show']);

        // Charges (cobranças)
        Route::get('charges', [ChargeController::class, 'index']);
        Route::post('charges', [ChargeController::class, 'store']);
        Route::get('charges/{id}', [ChargeController::class, 'show']);
        Route::post('charges/{id}/cancel', [ChargeController::class, 'cancel']);

        // Payments (direct, server-to-server)
        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('payments', [PaymentController::class, 'store']);
        Route::get('payments/{id}', [PaymentController::class, 'show']);
        Route::post('payments/{id}/cancel', [PaymentController::class, 'cancel']);

        // Balance
        Route::get('balance', [BalanceController::class, 'index']);
        Route::get('balance/history', [BalanceController::class, 'history']);
        Route::get('balance/advance/options', [BalanceController::class, 'advanceOptions']);
        Route::post('balance/advance', [BalanceController::class, 'requestAdvance']);

        // Payouts
        Route::get('payouts', [PayoutController::class, 'index']);
        Route::post('payouts', [PayoutController::class, 'store']);
        Route::get('payouts/{id}', [PayoutController::class, 'show']);
        Route::post('payouts/{id}/confirm', [PayoutController::class, 'confirm']);
        Route::post('payouts/{id}/fail', [PayoutController::class, 'fail']);

        // Webhooks
        Route::get('webhooks', [WebhookController::class, 'index']);
        Route::post('webhooks', [WebhookController::class, 'store']);

        // Simulation
        Route::get('simulation/rules', [SimulationController::class, 'rules']);
        Route::post('simulate/payment', [SimulationController::class, 'simulate']);
    });
});
