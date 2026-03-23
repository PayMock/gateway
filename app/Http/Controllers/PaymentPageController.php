<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\Charges\ChargeService;
use App\Services\Payments\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Serves the public payment page accessed via QR code.
 */
final class PaymentPageController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
        private readonly ChargeService $chargeService,
    ) {
    }

    /**
     * Show payment page or process payment confirmation.
     */
    public function show(Request $request, string $token): View|Response
    {
        $publicId = $this->qrCodeService->validateToken($token);

        if ($publicId === null) {
            return response(view('payment.expired'), 410);
        }

        $transaction = Transaction::where('public_id', $publicId)->first();

        if ($transaction === null) {
            abort(404);
        }

        if ($transaction->status === 'approved') {
            return view('payment.already_paid', compact('transaction'));
        }

        return view('payment.show', compact('transaction', 'token'));
    }

    /**
     * Process the payment confirmation (simulated user action on payment page).
     */
    public function confirm(Request $request, string $token): Response
    {
        $publicId = $this->qrCodeService->validateToken($token);

        if ($publicId === null) {
            return response(view('payment.expired'), 410);
        }

        $transaction = Transaction::where('public_id', $publicId)->first();

        if ($transaction === null) {
            abort(404);
        }

        if ($transaction->status === 'approved') {
            return response(view('payment.already_paid', compact('transaction')), 409);
        }

        $transaction->status = 'approved';
        $transaction->save();

        // If this transaction is linked to a charge, mark the charge as paid too
        if ($transaction->charge_id !== null) {
            $charge = $transaction->charge;

            if ($charge !== null && $charge->isPending()) {
                $this->chargeService->markAsPaid($charge);
            }
        }

        return response(view('payment.success', compact('transaction')));
    }
}
