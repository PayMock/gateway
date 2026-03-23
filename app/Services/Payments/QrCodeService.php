<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Generates signed QR code tokens for payment pages.
 *
 * QR token contains: transaction_id, expiration timestamp, HMAC signature.
 * QR content: https://gateway.local/pay/{signed_token}
 */
final class QrCodeService
{
    public function generateToken(Transaction $transaction): string
    {
        $expiresAt = now()->addMinutes(
            config('gateway.qr_expiry_minutes', 30)
        )->timestamp;

        $payload = $transaction->public_id . '.' . $expiresAt;

        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return base64_encode($payload . '.' . $signature);
    }

    public function generateUrl(Transaction $transaction): string
    {
        $token = $this->generateToken($transaction);

        return URL::to('/pay/' . $token);
    }

    public function generateQrCodeSvg(Transaction $transaction): string
    {
        $url = $this->generateUrl($transaction);

        return QrCode::format('svg')->size(200)->generate($url);
    }

    /**
     * Returns the QR code as a base64-encoded SVG string.
     * Use as: data:image/svg+xml;base64,{result}
     */
    public function generateBase64(Transaction $transaction): string
    {
        $svg = $this->generateQrCodeSvg($transaction);

        return base64_encode($svg);
    }

    /**
     * Validates a QR token and returns the public payment ID or null on failure.
     */
    public function validateToken(string $token): ?string
    {
        $decoded = base64_decode($token, strict: true);

        if ($decoded === false) {
            return null;
        }

        $parts = explode('.', $decoded, 3);

        if (count($parts) !== 3) {
            return null;
        }

        [$publicId, $expiresAt, $signature] = $parts;

        $payload = $publicId . '.' . $expiresAt;
        $expected = hash_hmac('sha256', $payload, config('app.key'));

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        if ((int) $expiresAt < now()->timestamp) {
            return null;
        }

        return $publicId;
    }
}
