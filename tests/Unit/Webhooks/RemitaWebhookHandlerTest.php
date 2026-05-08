<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\RemitaWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class RemitaWebhookHandlerTest extends TestCase
{
    private function makeHandler(): RemitaWebhookHandler
    {
        return new RemitaWebhookHandler(['webhook_secret' => 'remita_secret'], 'remita');
    }

    private function makeRequest(array $payload): Request
    {
        $content = json_encode($payload);
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('Content-Type', 'application/json');
        return $request;
    }

    public function test_verify_signature_always_passes(): void
    {
        // Remita does not use HMAC webhooks
        $handler = $this->makeHandler();
        $request = $this->makeRequest(['responseCode' => '00', 'orderId' => 'REF_001']);

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_parse_payload_returns_webhook_event_on_success(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'responseCode'  => '00',
            'orderId'       => 'ORDER_001',
            'amount'        => 5000,
            'currency'      => 'NGN',
            'RRR'           => 'RRR12345678',
            'status'        => 'SUCCESSFUL',
            'payerEmail'    => 'test@example.com',
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('PAYMENT_SUCCESSFUL', $event->getEventType());
        $this->assertEquals('remita', $event->getGateway());
    }

    public function test_parse_payload_maps_pending_response_code(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'responseCode' => '021',
            'orderId'      => 'ORDER_001',
            'amount'       => 5000,
            'status'       => 'PENDING',
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('PAYMENT_PENDING', $event->getEventType());
    }

    public function test_parse_payload_maps_failure(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'responseCode' => '99',
            'orderId'      => 'ORDER_001',
            'amount'       => 5000,
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('PAYMENT_FAILED', $event->getEventType());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }
}
