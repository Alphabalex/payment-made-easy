<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\RazorpayWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class RazorpayWebhookHandlerTest extends TestCase
{
    private string $secret = 'razorpay_webhook_secret';

    private function makeHandler(): RazorpayWebhookHandler
    {
        return new RazorpayWebhookHandler(['webhook_secret' => $this->secret], 'razorpay');
    }

    private function makeRequest(array $payload, ?string $signature = null): Request
    {
        $content = json_encode($payload);
        $sig = $signature ?? hash_hmac('sha256', $content, $this->secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('X-Razorpay-Signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_001']]]];

        $this->assertTrue($handler->verifySignature($this->makeRequest($payload)));
    }

    public function test_verify_signature_with_invalid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event' => 'payment.captured', 'payload' => []];

        $this->assertFalse($handler->verifySignature($this->makeRequest($payload, 'wrong_sig')));
    }

    public function test_parse_payload_returns_webhook_event(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event'   => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id'          => 'pay_001',
                        'amount'      => 500000,
                        'currency'    => 'INR',
                        'status'      => 'captured',
                        'order_id'    => 'order_001',
                        'description' => 'Test payment',
                        'email'       => 'test@example.com',
                        'contact'     => '+919876543210',
                        'method'      => 'card',
                        'captured'    => true,
                        'created_at'  => 1700000000,
                    ],
                ],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('payment.captured', $event->getEventType());
        $this->assertEquals('razorpay', $event->getGateway());
    }

    public function test_amount_converted_from_paise_to_rupees(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event'   => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id'       => 'pay_001',
                        'amount'   => 500000,
                        'currency' => 'INR',
                        'status'   => 'captured',
                        'captured' => true,
                        'created_at' => 1700000000,
                    ],
                ],
            ],
        ];

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

    public function test_verify_signature_bypassed_when_disabled(): void
    {
        $this->app['config']->set('payment-gateways.webhooks.verify_signature', false);

        $handler = $this->makeHandler();
        $payload = ['event' => 'payment.captured', 'payload' => []];

        $request = $this->makeRequest($payload, 'bad_sig');
        $this->assertTrue($handler->verifySignature($request));
    }
}
