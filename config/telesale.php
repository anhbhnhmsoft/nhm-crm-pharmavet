<?php

return [
    'realtime' => [
        'aggregate_notifications' => env('TELESALE_AGGREGATE_NOTIFICATIONS', true),
    ],
    'reports' => [
        'kpi_v2' => env('TELESALE_REPORTS_KPI_V2', true),
        'honor_board_v1' => env('TELESALE_REPORTS_HONOR_BOARD_V1', false),
    ],
    'dashboard' => [
        'ceo_v1' => env('TELESALE_DASHBOARD_CEO_V1', true),
    ],
];
