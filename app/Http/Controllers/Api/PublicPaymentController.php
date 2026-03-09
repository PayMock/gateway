<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public utility endpoints — intended for client-side (browser/mobile) use.
 *
 * Authentication: X-Public-Key header (pk_test_xxx)
 * Origin control: validated against project's allowed_origins list
 *
 * @tags Public Payments
 */
final class PublicPaymentController extends Controller
{
    /**
     * List available payment methods.
     *
     * Returns the payment methods supported by this gateway instance.
     *
     * @operationId publicListPaymentMethods
     */
    public function paymentMethods(): JsonResponse
    {
        $methods = config('gateway.payment_methods', []);

        $formatted = array_map(
            fn (string $method) => ['id' => $method, 'object' => 'payment_method'],
            $methods,
        );

        return response()->json([
            'object' => 'list',
            'data' => $formatted,
        ]);
    }
}
