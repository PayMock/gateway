<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Simulation Rules Registry
    |--------------------------------------------------------------------------
    |
    | List of rule classes in priority order (highest first).
    | Each rule must implement RuleInterface.
    |
    */

    'rules' => [
        App\Simulation\Rules\Card\CardStolenRule::class,
        App\Simulation\Rules\Card\GatewayDownRule::class,
        App\Simulation\Rules\Card\InvalidCvvRule::class,
        App\Simulation\Rules\Card\IssuerUnavailableRule::class,
        App\Simulation\Rules\Amount\FraudAmountRule::class,
        App\Simulation\Rules\Amount\ZeroAmountRule::class,
        App\Simulation\Rules\Amount\Lucky777Rule::class,
        App\Simulation\Rules\Amount\Timeout999Rule::class,
        App\Simulation\Rules\Amount\SlowProcessingRule::class,
        App\Simulation\Rules\Amount\FraudAmount666Rule::class,
        App\Simulation\Rules\Time\MaintenanceWindowRule::class,
        App\Simulation\Rules\Time\Friday13Rule::class,
        App\Simulation\Rules\User\AdminBlockedRule::class,
        App\Simulation\Rules\User\TestEmailAutoApproveRule::class,
        App\Simulation\Rules\Pix\PixFraudRule::class,
        App\Simulation\Rules\Pix\PixApprovedRule::class,
        App\Simulation\Rules\Pix\PixDuplicateWebhookRule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Card Number Triggers
    |--------------------------------------------------------------------------
    */

    'card_triggers' => [
        'stolen' => '0000',
        'invalid_cvv' => '1234',
        'issuer_unavailable' => '8888',
        'gateway_down' => '9999',
        'slow_issuer' => '4111',
        'fraud' => '1313',   // card contains/endswith 1313 → fraud
    ],

    /*
    |--------------------------------------------------------------------------
    | Amount Triggers
    |--------------------------------------------------------------------------
    */

    'amount_triggers' => [
        'fraud_contains' => '13',   // amount contains 13 → fraud
        'fraud_666' => 666,
        'lucky_777' => 777,
        'zero' => 0,
        'timeout' => 999,
        'slow_processing' => 1.23,
        'slow_approval_ends' => '.71',  // centavos .71 → slow
        'delayed_webhook' => 10,
        'review' => 0.01,
    ],

    /*
    |--------------------------------------------------------------------------
    | PIX Triggers (last 2 decimal digits)
    |--------------------------------------------------------------------------
    */

    'pix_triggers' => [
        'fraud_ends' => '.13',
        'approved_ends' => '.00',
        'duplicate_webhook_ends' => '.77',
    ],

    /*
    |--------------------------------------------------------------------------
    | Time-Based Triggers
    |--------------------------------------------------------------------------
    */

    'time_triggers' => [
        'maintenance_start' => '00:00',
        'maintenance_end' => '00:05',
        'friday13_weekday' => 5, // 5 = Friday (PHP date('N'))
        'friday13_day' => 13,
    ],
];
