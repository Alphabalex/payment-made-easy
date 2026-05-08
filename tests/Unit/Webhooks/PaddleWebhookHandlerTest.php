<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\PaddleWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaddleWebhookHandlerTest extends TestCase
{
    private string $secret = 'paddle_webhook_secret';

    private function makeHandler(): PaddleWebhookHandler
    {
        return new PaddleWebhookHandler(['webhook_secret' => $this->secret], 'paddle');
    }

    private function makeRequest(array $payload, ?string $headerOverride = null): Request
    {
        $content = json_encode($payload);
        $ts = '1700000000';
        $sig = $headerOverride ?? ('ts=' . $ts . ';h1=' . hash_hmac('sha256', $ts . ':' . $content, $this->secret));

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('Paddle-Signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_paddle_header(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event_type' => 'transaction.completed', 'data' => ['id' => 'txn_001']];

        $this->assertTrue($handler->verifySignature($this->makeRequest($payload)));
    }

    public function test_verify_signature_fails_with_missing_header(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event_type' => 'transaction.completed', 'data' => ['id' => 'txn_001']];

        $content = json_encode($payload);
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        // No Paddle-Signature header

        $this->assertFalse($handler->verifySignature($request));
    }

    public function test_verify_signature_fails_with_wrong_hash(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event_type' => 'transaction.completed', 'data' => ['id' => 'txn_001']];

        $request = $this->makeRequest($payload, 'ts=1700000000;h1=wronghash');

        $this->assertFalse($handler->verifySignature($request));
    }

    public function test_parse_payload_transaction_completed(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event_type' => 'transaction.completed',
            'data' => [
                'id'     => 'txn_001',
                'status' => 'completed',
                'details' => [
                    'totals' => ['grand_total' => '2999', 'currency_code' => 'USD'],
                ],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('transaction.completed', $event->getEventType());
        $this->assertEquals('paddle', $event->getGateway());
    }

    public function test_parse_subscription_activated_event(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event_type' => 'subscription.activated',
            'data' => [
                'id'         => 'sub_001',
                'status'     => 'active',
                'items'      => [['price' => ['product_id' => 'pro_001']]],
                'customer_id' => 'ctm_001',
                'next_billed_at' => '2024-02-01T00:00:00Z',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('subscription.activated', $event->getEventType());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }

    public function test_verify_signature_bypassed_when_disabled(): void
    {
        $this->app['config']->set('payment-gateways.webhooks.verify_signature', false);

        $handler = $this->makeHandler();
        $payload = ['event_type' => 'transaction.completed', 'data' => []];

        $content = json_encode($payload);
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        // No signature header

        $this->assertTrue($handler->verifySignature($request));
    }
}
