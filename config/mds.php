<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Observability admins
    |--------------------------------------------------------------------------
    | Comma-separated list of email addresses allowed to view Horizon, Pulse,
    | and Telescope dashboards in non-local environments.
    */
    'observability_admins' => env('OBSERVABILITY_ADMINS', ''),

    /*
    |--------------------------------------------------------------------------
    | BIR
    |--------------------------------------------------------------------------
    */
    'bir' => [
        'timezone' => env('BIR_TIMEZONE', 'Asia/Manila'),
        'vat_rate' => (float) env('BIR_VAT_RATE', 0.12),
    ],
];
