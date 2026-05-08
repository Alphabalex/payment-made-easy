<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\SeerbitWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class SeerbitWebhookHandlerTest extends TestCase
{
    private string $secret = 'seerbit_webhook_secret';

    private function makeHandler(): SeerbitWebhookHandler
    {
        return new SeerbitWebhookHandler(['webhook_secret' => $this->secret], 'seerbit');
    }

    private function makeRequest(array $payload, ?string $signature = null): Request
    {
        $content = json_encode($payload);
        $sig = $signature ?? hash_hmac('sha256', $content, $this->secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('x-seerbit-signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['eventType' => 'PAYMENT', 'data' => ['paymentReference' => 'REF_001']];

        $this->assertTrue($handler->verifySignature($this->makeRequest($payload)));
    }

    public function test_verify_signature_with_invalid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['eventType' => 'PAYMENT', 'data' => ['paymentReference' => 'REF_001']];

        $this->assertFalse($handler->verifySignature($this->makeRequest($payload, 'wrong_sig')));
    }

    public function test_parse_payload_returns_webhook_event_interface(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'eventType' => 'PAYMENT',
            'data' => [
                'paymentReference'        => 'REF_001',
                'amount'                  => 5000,
                'currency'                => 'NGN',
                'transactionStatus'       => 'SUCCESSFUL',
                'email'                   => 'test@example.com',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('PAYMENT', $event->getEventType());
        $this->assertEquals('seerbit', $event->getGateway());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{invalid_json}');
        $request->headers->set('Content-Type', 'application/json');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }

    public function test_verify_signature_bypassed_when_disabled(): void
    {
        $this->app['config']->set('payment-gateways.webhooks.verify_signature', false);

        $handler = $this->makeHandler();
        $payload = ['eventType' => 'PAYMENT', 'data' => ['paymentReference' => 'REF_001']];

        $request = $this->makeRequest($payload, 'bad_sig');

        $this->assertTrue($handler->verifySignature($request));
    }
}
