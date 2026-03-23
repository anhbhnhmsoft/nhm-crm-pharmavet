<?php

return [
    'realtime' => [
        'aggregate_notifications' => env('TELESALE_AGGREGATE_NOTIFICATIONS', true),
    ],
    'reports' => [
        'kpi_v2' => env('TELESALE_REPORTS_KPI_V2', true),
    ],
    'dashboard' => [
        'ceo_v1' => env('TELESALE_DASHBOARD_CEO_V1', true),
    ],
];
