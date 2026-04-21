<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessFacebookLeadJob;
use App\Models\FacebookLead;
use App\Services\Integrations\MetaBusinessService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FacebookWebhookTest extends TestCase
{
    public function test_it_returns_challenge_when_verify_token_matches(): void
    {
        config()->set('services.facebook.webhook_verify_token', 'verify-me');

        $this->get('/webhook/facebook?hub_verify_token=verify-me&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_it_queues_process_job_for_new_leadgen_payload(): void
    {
        Queue::fake();

        $facebookLead = new FacebookLead(['id' => 99]);
        $facebookLead->id = 99;
        $facebookLead->wasRecentlyCreated = true;

        $this->mock(MetaBusinessService::class, function ($mock) use ($facebookLead) {
            $mock->shouldReceive('queueLeadFromWebhook')
                ->once()
                ->andReturn(\App\Core\ServiceReturn::success($facebookLead));
        });

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'leadgen',
                            'value' => [
                                'page_id' => 'page_1',
                                'leadgen_id' => 'lead_1',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/webhook/facebook', $payload)
            ->assertOk()
            ->assertSee('OK');

        Queue::assertPushed(ProcessFacebookLeadJob::class, fn (ProcessFacebookLeadJob $job) => $job->facebookLeadId === 99);
    }

    public function test_it_rejects_invalid_webhook_signature(): void
    {
        config()->set('services.facebook.webhook_app_secret', 'secret-key');

        $this->withHeader('X-Hub-Signature-256', 'sha256=invalid-signature')
            ->postJson('/webhook/facebook', [
                'entry' => [],
            ])
            ->assertForbidden();
    }
}
