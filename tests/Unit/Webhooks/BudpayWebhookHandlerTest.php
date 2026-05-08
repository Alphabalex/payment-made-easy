<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\BudpayWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class BudpayWebhookHandlerTest extends TestCase
{
    private string $secret = 'budpay_webhook_secret';

    private function makeHandler(): BudpayWebhookHandler
    {
        return new BudpayWebhookHandler(['webhook_secret' => $this->secret], 'budpay');
    }

    private function makeRequest(array $payload, ?string $signature = null): Request
    {
        $content = json_encode($payload);
        $sig = $signature ?? hash_hmac('sha512', $content, $this->secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('x-budpay-signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['notify' => 'transaction', 'notifyType' => 'successful', 'data' => ['id' => 'REF_001']];

        $this->assertTrue($handler->verifySignature($this->makeRequest($payload)));
    }

    public function test_verify_signature_with_invalid_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['notify' => 'transaction', 'notifyType' => 'successful', 'data' => ['id' => 'REF_001']];

        $this->assertFalse($handler->verifySignature($this->makeRequest($payload, 'wrong_sig')));
    }

    public function test_parse_payload_returns_webhook_event(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'notify'     => 'transaction',
            'notifyType' => 'successful',
            'data'       => [
                'id'        => 'ORDER_001',
                'amount'    => '5000.00',
                'currency'  => 'NGN',
                'status'    => 'success',
                'customer'  => ['email' => 'test@example.com'],
                'paidAt'    => '2024-01-01T12:00:00',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('transaction.successful', $event->getEventType());
        $this->assertEquals('budpay', $event->getGateway());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }
}
