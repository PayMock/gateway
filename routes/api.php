<?php

use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SimulationController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\AuthenticateProject;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PayMock API Routes — v1
|--------------------------------------------------------------------------
|
| Stripe-style REST API. All authenticated routes require:
|   Authorization: Bearer sk_test_xxx
|
| Special headers:
|   Idempotency-Key: <string>   — prevents duplicate payments
|   X-PayMock-Rule: <rule_id>  — forces a specific simulation outcome
|
*/

// Public — project creation (no auth required to bootstrap)
Route::prefix('v1')->group(function () {
    Route::post('projects', [ProjectController::class, 'store']);

    // Authenticated routes
    Route::middleware(AuthenticateProject::class)->group(function () {
        // Project
        Route::get('projects/me', [ProjectController::class, 'show']);

        // Payments
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

        // Webhooks
        Route::get('webhooks', [WebhookController::class, 'index']);
        Route::post('webhooks', [WebhookController::class, 'store']);

        // Simulation
        Route::get('simulation/rules', [SimulationController::class, 'rules']);
        Route::post('simulate/payment', [SimulationController::class, 'simulate']);
    });
});
