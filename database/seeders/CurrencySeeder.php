<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $apiKey = config('services.exchangerate.api_key');
        $apiBaseUrl = rtrim(config('services.exchangerate.base_url', 'https://v6.exchangerate-api.com/v6'), '/');

        if (empty($apiKey)) {
            $this->command?->warn('Missing services.exchangerate.api_key. Skip CurrencySeeder.');
            return;
        }

        $response = Http::retry(3, 500)
            ->timeout(15)
            ->acceptJson()
            ->get("{$apiBaseUrl}/{$apiKey}/latest/USD");

        if (!$response->successful()) {
            $this->command?->error('CurrencySeeder failed: API request unsuccessful.');
            return;
        }

        $payload = $response->json();
        $rates = $payload['conversion_rates'] ?? [];

        if (!is_array($rates) || empty($rates)) {
            $this->command?->error('CurrencySeeder failed: conversion_rates is empty.');
            return;
        }

        foreach (array_keys($rates) as $code) {
            if (!is_string($code) || strlen($code) !== 3) {
                continue;
            }

            Currency::query()->updateOrCreate(
                ['code' => strtoupper($code)],
                ['code' => strtoupper($code)]
            );
        }

        $this->command?->info('CurrencySeeder completed successfully.');
    }
}

