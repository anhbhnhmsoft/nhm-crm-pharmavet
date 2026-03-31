<?php

return [
    'features' => [
        'integration_v2' => env('MARKETING_INTEGRATION_V2', false),
        'ranking_v2' => env('MARKETING_RANKING_V2', false),
        'budget_kpi_v1' => env('MARKETING_BUDGET_KPI_V1', false),
    ],
    'website_v2' => [
        'auth_header' => env('MARKETING_WEBSITE_AUTH_HEADER', 'X-Website-Token'),
    ],
    'facebook' => [
        'capi_secret' => env('MARKETING_CAPI_SECRET'),
        'retry_max' => env('MARKETING_FACEBOOK_RETRY_MAX', 5),
        'retry_backoff_seconds' => [60, 120, 300, 600, 900],
    ],
];
