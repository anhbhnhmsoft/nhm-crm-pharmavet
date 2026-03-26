<?php

return [
    'features' => [
        'stock_v2' => env('WAREHOUSE_STOCK_V2', true),
        'shipping_sync_v1' => env('WAREHOUSE_SHIPPING_SYNC_V1', true),
        'reports_v1' => env('WAREHOUSE_REPORTS_V1', false),
        'advanced_inventory_v1' => env('WAREHOUSE_ADVANCED_INVENTORY_V1', false),
    ],

    'shipping' => [
        'webhook_token' => env('GHN_WEBHOOK_TOKEN'),
    ],
];
