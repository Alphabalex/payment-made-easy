<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\InterswitchWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class InterswitchWebhookHandlerTest extends TestCase
{
    private string $secret = 'isw_client_secret';

    private function makeHandler(): InterswitchWebhookHandler
    {
        return new InterswitchWebhookHandler(['client_secret' => $this->secret], 'interswitch');
    }

    private function makeRequest(array $payload, ?string $signature = null): Request
    {
        $content = json_encode($payload);
        $sig = $signature ?? hash_hmac('sha512', $content, $this->secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('x-interswitch-signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['responseCode' => '00', 'transactionReference' => 'REF_001'];

        $this->assertTrue($handler->verifySignature($this->makeRequest($payload)));
    }

    public function test_verify_signature_with_invalid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['responseCode' => '00', 'transactionReference' => 'REF_001'];

        $this->assertFalse($handler->verifySignature($this->makeRequest($payload, 'wrong')));
    }

    public function test_parse_payload_maps_00_to_successful(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'responseCode'         => '00',
            'responseDescription'  => 'Approved by Financial Institution',
            'transactionReference' => 'ORDER_001',
            'amount'               => 500000,
            'customerEmail'        => 'test@example.com',
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('PAYMENT_SUCCESSFUL', $event->getEventType());
        $this->assertEquals('interswitch', $event->getGateway());
    }

    public function test_parse_payload_maps_T0_to_pending(): void
    {
        $handler = $this->makeHandler();
        $payload = ['responseCode' => 'T0', 'transactionReference' => 'ORDER_001', 'amount' => 500000];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('PAYMENT_PENDING', $event->getEventType());
    }

    public function test_parse_payload_maps_other_codes_to_failed(): void
    {
        $handler = $this->makeHandler();
        $payload = ['responseCode' => '05', 'transactionReference' => 'ORDER_001', 'amount' => 500000];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('PAYMENT_FAILED', $event->getEventType());
    }

    public function test_amount_converted_from_kobo(): void
    {
        $handler = $this->makeHandler();
        $payload = ['responseCode' => '00', 'transactionReference' => 'REF_001', 'amount' => 500000];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals(5000.0, $event->getData()['amount']);
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }
}
