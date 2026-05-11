<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\MonnifyWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MonnifyWebhookHandlerTest extends TestCase
{
    private string $secret = 'monnify_webhook_secret';

    private function makeHandler(): MonnifyWebhookHandler
    {
        return new MonnifyWebhookHandler(['webhook_secret' => $this->secret], 'monnify');
    }

    private function makeRequest(array $payload, ?string $signature = null): Request
    {
        $content = json_encode($payload);
        $sig = $signature ?? base64_encode(hash_hmac('sha512', $content, $this->secret, true));

        $request = Request::create('/webhook', 'POST', [], [], [], [], $content);
        $request->headers->set('monnify-signature', $sig);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_ensure_signing_secret_passes_with_secret_key_only(): void
    {
        $this->app['config']->set('payment-gateways.webhooks.verify_signature', true);
        $this->app['config']->set('payment-gateways.webhooks.require_signing_secret', true);

        $handler = new MonnifyWebhookHandler(['secret_key' => 'api_secret_only'], 'monnify');
        $handler->ensureSigningSecretConfiguredWhenRequired();

        $this->assertTrue(true);
    }

    public function test_verify_signature_with_valid_base64_hmac(): void
    {
        $handler = $this->makeHandler();
        $payload = ['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => ['paymentReference' => 'REF_001']];

        $this->assertTrue($handler->verifySignature($this->makeRequest($payload)));
    }

    public function test_verify_signature_with_invalid_signature(): void
    {
        $handler = $this->makeHandler();
        $payload = ['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => ['paymentReference' => 'REF_001']];

        $this->assertFalse($handler->verifySignature($this->makeRequest($payload, 'invalid_base64_sig')));
    }

    public function test_parse_payload_returns_webhook_event(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'eventType' => 'SUCCESSFUL_TRANSACTION',
            'eventData' => [
                'paymentReference'  => 'ORDER_001',
                'amountPaid'        => 5000,
                'currency'          => 'NGN',
                'paymentStatus'     => 'PAID',
                'customer'          => ['email' => 'test@example.com'],
                'paidOn'            => '2024-01-01T12:00:00',
                'paymentMethod'     => 'CARD',
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('SUCCESSFUL_TRANSACTION', $event->getEventType());
        $this->assertEquals('monnify', $event->getGateway());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }

    public function test_reserved_account_funded_event_parses_correctly(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'eventType' => 'RESERVED_ACCOUNT_FUNDED',
            'eventData' => [
                'paymentReference' => 'MNNFY_REF',
                'amountPaid'       => 10000,
                'currency'         => 'NGN',
                'customer'         => ['email' => 'test@example.com'],
                'paidOn'           => '2024-01-01',
                'destinationAccountInformation' => [
                    'accountNumber' => '1234567890',
                    'bankName'      => 'Access Bank',
                ],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('RESERVED_ACCOUNT_FUNDED', $event->getEventType());
        $data = $event->getData();
        $this->assertArrayHasKey('account_number', $data);
        $this->assertEquals('1234567890', $data['account_number']);
    }
}
