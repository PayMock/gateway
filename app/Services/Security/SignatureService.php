<?php

namespace App\Services\Security;

/**
 * Signs webhook payloads with HMAC-SHA256.
 * Header format: PayMock-Signature: t=timestamp,v1=signature
 */
final class SignatureService
{
    public function sign(string $payload, string $secret, int $timestamp): string
    {
        $signedPayload = $timestamp . '.' . $payload;

        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return 't=' . $timestamp . ',v1=' . $signature;
    }

    public function verify(string $payload, string $secret, string $header): bool
    {
        $parts = $this->parseHeader($header);

        if ($parts === null) {
            return false;
        }

        $expected = $this->sign($payload, $secret, (int) $parts['timestamp']);

        return hash_equals($expected, $header);
    }

    /** @return array{timestamp: string, signature: string}|null */
    private function parseHeader(string $header): ?array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key === 't' ? 'timestamp' : 'signature'] = $value;
        }

        if (! isset($parts['timestamp'], $parts['signature'])) {
            return null;
        }

        return $parts;
    }
}
