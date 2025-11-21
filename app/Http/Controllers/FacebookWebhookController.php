<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFacebookLeadJob;
use Illuminate\Http\Request;
use App\Services\Integrations\MetaBusinessService;

class FacebookWebhookController
{

    protected MetaBusinessService $metaBusinessService;

    /**
     * @param MetaBusinessService $metaBusinessService
     * @return void
     */
    public function __construct(MetaBusinessService $metaBusinessService)
    {
        $this->metaBusinessService = $metaBusinessService;
    }

    public function verify(Request $request)
    {
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        $ret = $this->metaBusinessService->verifyIntegrationByWebhookToken($token);
        if ($ret->isSuccess()) {
            return response($challenge, 200);
        }

        return response(__('messages.meta_business.error.invalid_verify_token'), 403);
    }

    public function receive(Request $request)
    {
        $payload = $request->all();

        foreach ((array)($payload['entry'] ?? []) as $entry) {
            foreach ((array)($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $pageId = (string)($change['value']['page_id'] ?? '');
                $leadId = (string)($change['value']['leadgen_id'] ?? '');

                if (!$pageId || !$leadId) {
                    continue;
                }

                $ret = $this->metaBusinessService->findIntegrationByPageId($pageId);
                if ($ret->isError()) {
                    continue;
                }

                dispatch(new ProcessFacebookLeadJob($ret->getData()->id, $leadId, $pageId));
            }
        }

        return response('OK', 200);
    }
}
