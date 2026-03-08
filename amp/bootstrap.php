<?php

/**
 * AMP Bootstrap — shared setup for all AMP worker scripts.
 *
 * Loads the Laravel application and sets up async-safe connections.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Boot the Laravel kernel for console context
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
