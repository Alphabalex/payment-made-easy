<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\PaystackWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaystackWebhookHandlerTest extends TestCase
{
    private string $secret = 'test_paystack_webhook_secret';

    private function makeHandler(): PaystackWebhookHandler
    {
        return new PaystackWebhookHandler(['webhook_secret' => $this->secret], 'paystack');
    }

    private function makeRequest(array $payload, ?string $signature = null): Request
    {
        $content = json_encode($payload);
        $sig = $signature ?? hash_hmac('sha512', $content, $this->secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('x-paystack-signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'ref_001']];

        $request = $this->makeRequest($payload);

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_verify_signature_with_invalid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'ref_001']];

        $request = $this->makeRequest($payload, 'invalid_signature');

        $this->assertFalse($handler->verifySignature($request));
    }

    public function test_parse_payload_returns_webhook_event_interface(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event' => 'charge.success',
            'data'  => [
                'reference'    => 'PAYSTACK_REF_001',
                'amount'       => 500000,
                'currency'     => 'NGN',
                'status'       => 'success',
                'customer'     => ['email' => 'user@example.com'],
                'gateway_response' => 'Successful',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('charge.success', $event->getEventType());
        $this->assertEquals('paystack', $event->getGateway());
    }

    public function test_amount_is_converted_from_kobo_to_naira(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event' => 'charge.success',
            'data'  => [
                'reference' => 'ref_001',
                'amount'    => 500000, // 500,000 kobo = 5,000 NGN
                'currency'  => 'NGN',
                'status'    => 'success',
                'customer'  => ['email' => 'user@example.com'],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));
        $data  = $event->getData();

        $this->assertEquals(5000.0, $data['amount']);
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'not-valid-json');
        $request->headers->set('Content-Type', 'application/json');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }

    public function test_verify_signature_bypassed_when_disabled(): void
    {
        config(['payment-gateways.webhooks.verify_signature' => false]);

        $handler = $this->makeHandler();
        $request = $this->makeRequest(['event' => 'charge.success', 'data' => []], 'totally_wrong');

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_transfer_event_parses_correctly(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event' => 'transfer.success',
            'data'  => [
                'transfer_code' => 'TRF_001',
                'reference'     => 'REF_001',
                'amount'        => 100000,
                'currency'      => 'NGN',
                'status'        => 'success',
                'reason'        => 'Salary',
                'recipient'     => ['details' => ['account_name' => 'John Doe', 'account_number' => '0123456789', 'bank_name' => 'GTBank']],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));
        $data  = $event->getData();

        $this->assertEquals('transfer.success', $event->getEventType());
        $this->assertEquals('TRF_001', $data['transfer_code']);
        $this->assertEquals(1000.0, $data['amount']); // 100000 kobo → 1000 NGN
    }
}
