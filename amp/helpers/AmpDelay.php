<?php

namespace App\Amp\Helpers;

use function Amp\async;
use function Amp\delay as ampDelay;

/**
 * Helper for async delays in AMP workers.
 * Avoids blocking the event loop.
 */
final class AmpDelay
{
    public static function seconds(float $seconds): void
    {
        ampDelay($seconds);
    }

    public static function milliseconds(int $ms): void
    {
        ampDelay($ms / 1000);
    }
}
