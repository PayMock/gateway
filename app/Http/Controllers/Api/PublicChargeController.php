<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Services\Charges\ChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public charge payment endpoints — intended for client-side (browser/mobile) use.
 *
 * A charge must first be created via the private API (POST /v1/charges).
 * The customer then pays it here using a public key.
 *
 * Authentication: X-Public-Key header (pk_test_xxx)
 * Origin control: validated against project's allowed_origins list
 *
 * @tags Public Charges
 */
final class PublicChargeController extends Controller
{
    public function __construct(
        private readonly ChargeService $chargeService,
    ) {
    }

    /**
     * Pay a charge.
     *
     * Processes payment for an existing pending charge.
     *
     * For PIX: returns QR code URL and base64 image. The charge stays pending
     * until the customer visits the QR URL and confirms payment.
     *
     * For credit_card: runs the simulation engine immediately and returns the
     * final status (approved, failed, fraud, etc).
     *
     * @operationId publicPayCharge
     */
    public function pay(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $charge = $this->findOrFail($project, $id);

        if (!$charge->isPending()) {
            return response()->json([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'charge_already_processed',
                    'message' => "Charge '{$id}' has status '{$charge->status}' and cannot be paid.",
                ],
            ], 422);
        }

        $validated = $request->validate([
            'method' => 'required|in:pix,credit_card',
            'card_number' => 'required_if:method,credit_card|string|min:13|max:19',
            'card_holder_name' => 'required_if:method,credit_card|string|max:255',
            'card_expiry' => 'required_if:method,credit_card|string|max:7',
            'card_cvv' => 'required_if:method,credit_card|string|min:3|max:4',
        ]);

        if ($validated['method'] === 'pix') {
            return $this->handlePixPayment($charge);
        }

        return $this->handleCardPayment($charge, $validated);
    }

    /**
     * Get charge status.
     *
     * Returns minimal status information for polling from the client side.
     *
     * @operationId publicGetChargeStatus
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $project = $request->get('_project');
        $charge = $this->findOrFail($project, $id);

        return response()->json([
            'id' => $charge->public_id,
            'object' => 'charge',
            'status' => $charge->status,
            'amount' => (float) $charge->amount,
            'currency' => $charge->currency,
            'created' => $charge->created_at->timestamp,
        ]);
    }

    /**
     * Get charge QR code image.
     *
     * Returns the QR code SVG for PIX payments. Only available after a PIX
     * payment has been initiated via the pay endpoint.
     *
     * @operationId publicGetChargeQrCode
     */
    public function qrCode(Request $request, string $id): Response|JsonResponse
    {
        $project = $request->get('_project');
        $charge = $this->findOrFail($project, $id);

        // Find the pending PIX transaction linked to this charge
        $transaction = $charge->transactions()
            ->where('method', 'pix')
            ->whereNotNull('qr_code')
            ->latest()
            ->first();

        if ($transaction === null) {
            return response()->json([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'no_qr_code',
                    'message' => "Charge '{$id}' does not have a PIX QR code. Initiate a PIX payment first.",
                ],
            ], 404);
        }

        return response($transaction->qr_code, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-store');
    }

    private function handlePixPayment(Charge $charge): JsonResponse
    {
        $result = $this->chargeService->payWithPix($charge);

        return response()->json([
            'id' => $charge->public_id,
            'object' => 'charge',
            'status' => $charge->status,
            'method' => 'pix',
            'amount' => (float) $charge->amount,
            'currency' => $charge->currency,
            'payment' => [
                'transaction_id' => $result['transaction']->public_id,
                'qr_code_url' => $result['qr_code_url'],
                'qr_code_base64' => $result['qr_code_base64'],
                'qr_code_mime' => 'image/svg+xml',
                'expires_at' => $charge->expires_at?->timestamp,
            ],
            'created' => $charge->created_at->timestamp,
        ]);
    }

    private function handleCardPayment(Charge $charge, array $validated): JsonResponse
    {
        $transaction = $this->chargeService->payWithCard($charge, [
            'card_number' => $validated['card_number'],
            'card_holder_name' => $validated['card_holder_name'],
            'card_expiry' => $validated['card_expiry'],
            'card_cvv' => $validated['card_cvv'],
        ]);

        // Reload charge to get updated status
        $charge->refresh();

        return response()->json([
            'id' => $charge->public_id,
            'object' => 'charge',
            'status' => $charge->status,
            'method' => 'credit_card',
            'amount' => (float) $charge->amount,
            'currency' => $charge->currency,
            'payment' => [
                'transaction_id' => $transaction->public_id,
                'status' => $transaction->status,
                'failure_reason' => $transaction->failure_reason,
            ],
            'created' => $charge->created_at->timestamp,
        ]);
    }

    private function findOrFail(mixed $project, string $publicId): Charge
    {
        $charge = Charge::query()
            ->where('project_id', $project->id)
            ->where('public_id', $publicId)
            ->first();

        if ($charge === null) {
            abort(response()->json([
                'error' => [
                    'type' => 'invalid_request',
                    'code' => 'resource_not_found',
                    'message' => 'No such charge: ' . $publicId,
                ],
            ], 404));
        }

        return $charge;
    }
}
