<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\PayPalWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PayPalWebhookHandlerTest extends TestCase
{
    private function makeHandler(): PayPalWebhookHandler
    {
        return new PayPalWebhookHandler(['webhook_secret' => 'paypal_secret'], 'paypal');
    }

    private function makeRequest(array $payload, array $extraHeaders = []): Request
    {
        $content = json_encode($payload);
        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('Content-Type', 'application/json');

        foreach ($extraHeaders as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    private function paypalHeaders(): array
    {
        return [
            'PAYPAL-TRANSMISSION-ID'   => 'txid_001',
            'PAYPAL-TRANSMISSION-TIME' => '2024-01-01T12:00:00Z',
            'PAYPAL-CERT-URL'          => 'https://api.paypal.com/v1/notifications/certs/CERT-001',
            'PAYPAL-TRANSMISSION-SIG'  => 'fake_rsa_signature',
        ];
    }

    public function test_verify_signature_passes_when_required_headers_present(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];

        $request = $this->makeRequest($payload, $this->paypalHeaders());

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_verify_signature_fails_when_headers_missing(): void
    {
        $handler = $this->makeHandler();
        $payload = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];

        // No PayPal headers
        $request = $this->makeRequest($payload);

        $this->assertFalse($handler->verifySignature($request));
    }

    public function test_parse_payment_capture_completed(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'     => 'CAP_001',
                'status' => 'COMPLETED',
                'amount' => ['currency_code' => 'USD', 'value' => '50.00'],
                'purchase_units' => [
                    [
                        'reference_id' => 'ORDER_001',
                        'amount'       => ['currency_code' => 'USD', 'value' => '50.00'],
                        'payments'     => [
                            'captures' => [
                                ['id' => 'CAP_001', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '50.00']],
                            ],
                        ],
                    ],
                ],
                'payer' => ['email_address' => 'buyer@example.com', 'payer_id' => 'PAYER_001'],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload, $this->paypalHeaders()));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('PAYMENT.CAPTURE.COMPLETED', $event->getEventType());
        $this->assertEquals('paypal', $event->getGateway());
    }

    public function test_verify_signature_bypassed_when_disabled(): void
    {
        $this->app['config']->set('payment-gateways.webhooks.verify_signature', false);

        $handler = $this->makeHandler();
        $payload = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];

        // No headers — would normally fail
        $request = $this->makeRequest($payload);
        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }
}
