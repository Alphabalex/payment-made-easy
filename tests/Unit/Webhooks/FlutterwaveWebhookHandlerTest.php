<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\FlutterwaveWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class FlutterwaveWebhookHandlerTest extends TestCase
{
    private string $secret = 'flw_webhook_secret_hash';

    private function makeHandler(): FlutterwaveWebhookHandler
    {
        return new FlutterwaveWebhookHandler(['webhook_secret' => $this->secret], 'flutterwave');
    }

    private function makeRequest(array $payload, ?string $header = null): Request
    {
        $content = json_encode($payload);
        // Flutterwave sends the raw secret in verif-hash (not HMAC)
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('verif-hash', $header ?? $this->secret);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_correct_hash(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest(['event' => 'charge.completed', 'data' => []]);

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_verify_signature_with_wrong_hash(): void
    {
        $handler = $this->makeHandler();
        $request = $this->makeRequest(['event' => 'charge.completed', 'data' => []], 'wrong_hash');

        $this->assertFalse($handler->verifySignature($request));
    }

    public function test_parse_payload_returns_webhook_event(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event' => 'charge.completed',
            'data'  => [
                'id'          => 12345,
                'tx_ref'      => 'FLW_ORDER_001',
                'status'      => 'successful',
                'amount'      => 5000,
                'currency'    => 'NGN',
                'customer'    => ['email' => 'user@example.com'],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('charge.completed', $event->getEventType());
        $this->assertEquals('flutterwave', $event->getGateway());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'not-json');
        $request->headers->set('verif-hash', $this->secret);

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }

    public function test_flutterwave_amounts_not_divided_by_100(): void
    {
        // Flutterwave sends amounts in major units (NGN), not kobo
        $handler = $this->makeHandler();
        $payload = [
            'event' => 'charge.completed',
            'data'  => [
                'id'       => 1,
                'tx_ref'   => 'ref_001',
                'status'   => 'successful',
                'amount'   => 5000,  // 5,000 NGN (not converted)
                'currency' => 'NGN',
                'customer' => ['email' => 'user@example.com'],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));
        $data  = $event->getData();

        // Unlike Paystack, amount should NOT be divided by 100
        $this->assertEquals(5000, $data['amount']);
    }

    public function test_transfer_event_parses_correctly(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event' => 'transfer.completed',
            'data'  => [
                'id'             => 99,
                'reference'      => 'TREF_001',
                'amount'         => 10000,
                'currency'       => 'NGN',
                'status'         => 'SUCCESSFUL',
                'full_name'      => 'Jane Doe',
                'account_number' => '0987654321',
                'bank_name'      => 'GTBank',
                'narration'      => 'Payout',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));
        $data  = $event->getData();

        $this->assertEquals('transfer.completed', $event->getEventType());
        $this->assertEquals('TREF_001', $data['reference']);
        $this->assertEquals('Jane Doe', $data['recipient_name']);
    }
}
