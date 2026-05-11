<?php

namespace NexusPay\PaymentMadeEasy\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use NexusPay\PaymentMadeEasy\Contracts\WebhookLogSanitizerInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Jobs\ProcessWebhookJob;
use NexusPay\PaymentMadeEasy\WebhookManager;

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
            if (config('payment-gateways.webhooks.queue_events', false)) {
                ProcessWebhookJob::dispatch(
                    $gateway,
                    $request->getContent() !== false ? $request->getContent() : '',
                    $this->flattenHeaders($request)
                );

                return response('Webhook queued', 202);
            }

            $processed = $this->webhookManager->handle($gateway, $request);

            if (!$processed) {
                return response('Duplicate webhook ignored', 200);
            }

            return response('Webhook handled successfully', 200);
        } catch (WebhookException $e) {
            $this->logWebhookFailure($gateway, $e, $request);

            return response('Webhook handling failed', 400);
        } catch (\Exception $e) {
            $context = [
                'gateway' => $gateway,
                'error'   => $e->getMessage(),
            ];
            $traceConfig = config('payment-gateways.webhooks.log_unexpected_exception_trace');
            $includeTrace = $traceConfig === null
                ? (bool) config('app.debug', false)
                : (bool) $traceConfig;
            if ($includeTrace) {
                $context['trace'] = $e->getTraceAsString();
            }

            Log::error('Unexpected webhook error', $context);

            return response('Internal server error', 500);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function flattenHeaders(Request $request): array
    {
        $flat = [];
        foreach ($request->headers->all() as $name => $values) {
            $flat[$name] = is_array($values) ? (string) reset($values) : (string) $values;
        }

        return $flat;
    }

    protected function logWebhookFailure(string $gateway, WebhookException $e, Request $request): void
    {
        $context = [
            'gateway' => $gateway,
            'error'   => $e->getMessage(),
        ];

        if (config('payment-gateways.webhooks.log_detail', 'full') === 'full') {
            $payload = $request->all();
            if (config('payment-gateways.webhooks.log_sanitize', true)) {
                $payload = App::make(WebhookLogSanitizerInterface::class)->sanitize($payload);
            }
            $context['payload'] = $payload;
        }

        Log::error('Webhook handling failed', $context);
    }
}
