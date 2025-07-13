<?php

namespace NexusPay\PaymentMadeEasy\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use NexusPay\PaymentMadeEasy\WebhookManager;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class WebhookController extends Controller
{
    protected WebhookManager $webhookManager;

    public function __construct(WebhookManager $webhookManager)
    {
        $this->webhookManager = $webhookManager;
    }

    public function handle(Request $request, string $gateway): Response
    {
        if (!config('payment-gateways.webhooks.enabled', true)) {
            return response('Webhooks are disabled', 404);
        }

        try {
            $this->webhookManager->handle($gateway, $request);

            return response('Webhook handled successfully', 200);
        } catch (WebhookException $e) {
            Log::error('Webhook handling failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response('Webhook handling failed', 400);
        } catch (\Exception $e) {
            Log::error('Unexpected webhook error', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Internal server error', 500);
        }
    }
}
