<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhnShippingService
{
    /**
     * Fetch list of stores (shops) from GHN API using the provided token.
     * Returns an associative array [store_id => store_name].
     * In case of error, returns empty array and logs the issue.
     */
    public function fetchStores(string $apiToken): array
    {
        try {
            $response = Http::withHeaders([
                'Token' => $apiToken,
                'Content-Type' => 'application/json',
            ])->get('https://online-gateway.ghn.vn/shiip/public-api/v2/shop/all');

            if ($response->successful()) {
                $data = $response->json();
                $shops = $data['data']['shops'] ?? [];
                $options = [];
                foreach ($shops as $shop) {
                    $options[$shop['_id']] = $shop['name'];
                }
                return $options;
            }
        } catch (\Exception $e) {
            Log::error('GHN fetch stores error: ' . $e->getMessage());
        }
        return [];
    }
}
