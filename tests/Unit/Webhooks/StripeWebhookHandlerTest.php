<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\StripeWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class StripeWebhookHandlerTest extends TestCase
{
    private string $secret = 'whsec_test_secret';

    private function makeHandler(): StripeWebhookHandler
    {
        return new StripeWebhookHandler(['webhook_secret' => $this->secret], 'stripe');
    }

    /**
     * Build a Stripe-signed webhook request using the Stripe SDK's own
     * signing mechanism so we can test both verification paths.
     */
    private function makeSignedRequest(array $eventPayload): Request
    {
        $timestamp = time();
        $payload   = json_encode($eventPayload);
        $signed    = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signed, $this->secret);
        $header    = "t={$timestamp},v1={$signature}";

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request->headers->set('Stripe-Signature', $header);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function test_verify_signature_with_valid_stripe_header(): void
    {
        $handler = $this->makeHandler();
        $payload = $this->buildPaymentIntentPayload();
        $request = $this->makeSignedRequest($payload);

        // Stripe SDK validates the full signature — valid header should pass
        $result = $handler->verifySignature($request);
        $this->assertTrue($result);
    }

    public function test_verify_signature_with_missing_header(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('Content-Type', 'application/json');
        // No Stripe-Signature header

        $result = $handler->verifySignature($request);
        $this->assertFalse($result);
    }

    public function test_verify_signature_bypassed_when_disabled(): void
    {
        config(['payment-gateways.webhooks.verify_signature' => false]);

        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        // No signature at all — but it should be bypassed

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_parse_payload_throws_on_invalid_signature(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode($this->buildPaymentIntentPayload()));
        $request->headers->set('Stripe-Signature', 't=1,v1=invalid_sig');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }

    public function test_parse_payload_returns_event_with_correct_gateway(): void
    {
        $handler = $this->makeHandler();
        $payload = $this->buildPaymentIntentPayload();
        $request = $this->makeSignedRequest($payload);

        $event = $handler->parsePayload($request);

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('stripe', $event->getGateway());
        $this->assertEquals('payment_intent.succeeded', $event->getEventType());
    }

    public function test_amount_converted_from_cents(): void
    {
        $handler = $this->makeHandler();
        $payload = $this->buildPaymentIntentPayload(amount: 2999); // $29.99
        $request = $this->makeSignedRequest($payload);

        $event = $handler->parsePayload($request);
        $data  = $event->getData();

        $this->assertEquals(29.99, $data['amount']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildPaymentIntentPayload(int $amount = 5000): array
    {
        return [
            'id'     => 'evt_test_001',
            'object' => 'event',
            'type'   => 'payment_intent.succeeded',
            'data'   => [
                'object' => [
                    'id'            => 'pi_test_001',
                    'object'        => 'payment_intent',
                    'amount'        => $amount,
                    'currency'      => 'usd',
                    'status'        => 'succeeded',
                    'receipt_email' => 'user@example.com',
                    'metadata'      => ['reference' => 'ORDER_001'],
                    'created'       => time(),
                ],
            ],
        ];
    }
}
