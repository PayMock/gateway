<?php

namespace App\Services\Charges;

use App\Models\Charge;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\Payments\PaymentService;
use App\Services\Payments\QrCodeService;
use App\Services\Security\TokenGenerator;

/**
 * Orchestrates the charge lifecycle.
 *
 * A Charge is a payment request created by the merchant backend.
 * The customer then "pays" the charge via the public API:
 *   - PIX: returns QR code (pending until customer confirms via /pay page)
 *   - Credit card: runs simulation engine immediately
 */
final class ChargeService
{
    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
        private readonly PaymentService $paymentService,
        private readonly QrCodeService $qrCodeService,
    ) {
    }

    /**
     * Creates a new charge for the given project.
     *
     * @param array{
     *   amount: float,
     *   currency?: string,
     *   description?: string,
     *   customer_name?: string,
     *   customer_email?: string,
     *   metadata?: array<string, mixed>,
     *   expires_at?: string,
     * } $data
     */
    public function create(Project $project, array $data): Charge
    {
        return Charge::create([
            'project_id' => $project->id,
            'public_id' => $this->tokenGenerator->generateChargeId(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'BRL',
            'description' => $data['description'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'status' => 'pending',
            'metadata' => $data['metadata'] ?? null,
            'expires_at' => isset($data['expires_at']) ? $data['expires_at'] : null,
        ]);
    }

    /**
     * Pays a charge via PIX.
     *
     * The charge remains in "pending" status. The QR code points to a page
     * where the customer can simulate payment confirmation.
     *
     * @return array{
     *   transaction: Transaction,
     *   qr_code_url: string,
     *   qr_code_base64: string,
     * }
     */
    public function payWithPix(Charge $charge): array
    {
        // Create a pending transaction directly — no simulation engine for PIX
        $transaction = Transaction::create([
            'project_id' => $charge->project_id,
            'charge_id' => $charge->id,
            'public_id' => $this->tokenGenerator->generatePaymentId(),
            'amount' => $charge->amount,
            'currency' => $charge->currency,
            'method' => 'pix',
            'status' => 'pending',
            'description' => $charge->description,
            'customer_name' => $charge->customer_name,
            'customer_email' => $charge->customer_email,
        ]);

        // Generate QR code pointing to the confirmation page
        $transaction->qr_code = $this->qrCodeService->generateQrCodeSvg($transaction);
        $transaction->qr_code_url = $this->qrCodeService->generateUrl($transaction);
        $transaction->save();

        return [
            'transaction' => $transaction,
            'qr_code_url' => $transaction->qr_code_url,
            'qr_code_base64' => $this->qrCodeService->generateBase64($transaction),
        ];
    }

    /**
     * Pays a charge via credit card.
     *
     * Runs the simulation engine. If approved, marks the charge as paid immediately.
     *
     * @param array{
     *   card_number: string,
     *   card_holder_name: string,
     *   card_expiry: string,
     *   card_cvv: string,
     * } $cardData
     */
    public function payWithCard(Charge $charge, array $cardData): Transaction
    {
        $data = [
            'amount' => $charge->amount,
            'currency' => $charge->currency,
            'method' => 'credit_card',
            'description' => $charge->description,
            'customer_name' => $charge->customer_name,
            'customer_email' => $charge->customer_email,
            'card_number' => $cardData['card_number'],
            'charge_id' => $charge->id,
        ];

        $project = $charge->project;

        $transaction = $this->paymentService->createPayment($project, $data);

        if ($transaction->status === 'approved') {
            $charge->status = 'paid';
            $charge->save();
        }

        return $transaction;
    }

    /**
     * Marks a charge as paid (called when the PIX QR confirmation happens).
     */
    public function markAsPaid(Charge $charge): void
    {
        $charge->status = 'paid';
        $charge->save();
    }
}
