<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\IntegrationEntity;
use App\Models\IntegrationToken;
use Laravel\Socialite\Facades\Socialite;
use App\Common\Constants\Marketing\IntegrationTokenType;
use Illuminate\Support\Facades\Http;

class FacebookOAuthService
{
    public function redirectUrl(): string
    {
        return Socialite::driver('facebook')
            // ->scopes(['pages_show_list', 'leads_retrieval', 'pages_read_engagement', 'business_management'])
            ->stateless()
            ->redirect()
            ->getTargetUrl()
            ;
    }

    public function handleCallback(Integration $integration)
    {
        $facebookUser = Socialite::driver('facebook')->stateless()->user();

        // Save user long-lived token
        $longLivedToken = $this->exchangeLongLivedToken($facebookUser->token);

        $integration->tokens()->create([
            'type' => IntegrationTokenType::USER_LONG_LIVED_TOKEN->value,
            'token' => encrypt($longLivedToken['access_token']),
            'expires_at' => now()->addSeconds($longLivedToken['expires_in']),
            'scopes' => [],
        ]);

        // Get pages
        $pages = Http::get(
            "https://graph.facebook.com/v20.0/me/accounts?access_token={$facebookUser->token}"
        )->json('data');

        foreach ($pages as $page) {
            $entity = $integration->entities()->updateOrCreate(
                ['external_id' => $page['id'], 'type' => 'page'],
                [
                    'name' => $page['name'],
                    'metadata' => ['category' => $page['category'] ?? null]
                ]
            );

            // Save page token
            $integration->tokens()->create([
                'entity_id' => $entity->id,
                'type' => IntegrationTokenType::PAGE_ACCESS_TOKEN->value,
                'token' => encrypt($page['access_token']),
                'scopes' => [],
                'expires_at' => null,
            ]);
        }

        return true;
    }

    protected function exchangeLongLivedToken(string $shortToken): array
    {
        return Http::get('https://graph.facebook.com/v20.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'fb_exchange_token' => $shortToken,
        ])->json();
    }
}
