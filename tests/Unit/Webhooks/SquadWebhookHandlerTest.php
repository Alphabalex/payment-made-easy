<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\SquadWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class SquadWebhookHandlerTest extends TestCase
{
    private string $secret = 'squad_webhook_secret';

    private function makeHandler(): SquadWebhookHandler
    {
        return new SquadWebhookHandler(['webhook_secret' => $this->secret], 'squad');
    }

    private function makeRequest(array $payload, ?string $signature = null): Request
    {
        $content = json_encode($payload);
        $sig = $signature ?? hash_hmac('sha512', $content, $this->secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('x-squad-signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['Event' => 'charge_successful', 'Body' => ['transaction_ref' => 'REF_001']];

        $this->assertTrue($handler->verifySignature($this->makeRequest($payload)));
    }

    public function test_verify_signature_with_invalid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['Event' => 'charge_successful', 'Body' => ['transaction_ref' => 'REF_001']];

        $this->assertFalse($handler->verifySignature($this->makeRequest($payload, 'wrong_signature')));
    }

    public function test_parse_payload_returns_webhook_event(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'Event' => 'charge_successful',
            'Body' => [
                'transaction_ref'    => 'ORDER_001',
                'amount'             => 500000,
                'currency'           => 'NGN',
                'transaction_status' => 'Success',
                'email'              => 'test@example.com',
                'createdAt'          => '2024-01-01T12:00:00',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('charge_successful', $event->getEventType());
        $this->assertEquals('squad', $event->getGateway());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }

    public function test_amount_converted_from_kobo_to_naira(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'Event' => 'charge_successful',
            'Body' => [
                'transaction_ref'    => 'REF_001',
                'transaction_amount' => 500000,  // Squad uses transaction_amount for charge events
                'currency'           => 'NGN',
                'transaction_status' => 'Success',
                'email'              => 'test@example.com',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals(5000.0, $event->getData()['amount']);
    }
}
