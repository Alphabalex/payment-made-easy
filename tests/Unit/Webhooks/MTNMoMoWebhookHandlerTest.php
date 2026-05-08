<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\MTNMoMoWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MTNMoMoWebhookHandlerTest extends TestCase
{
    private function makeHandler(): MTNMoMoWebhookHandler
    {
        return new MTNMoMoWebhookHandler([], 'mtnmomo');
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
        // MTN MoMo uses callback URL security, not HMAC
        $handler = $this->makeHandler();
        $request = $this->makeRequest(['status' => 'SUCCESSFUL', 'externalId' => 'EXT_001']);

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_parse_successful_collection_payment(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'financialTransactionId' => 'FIN_TXN_001',
            'externalId'             => 'ORDER_001',
            'amount'                 => '1000',
            'currency'               => 'UGX',
            'payer'                  => ['partyIdType' => 'MSISDN', 'partyId' => '256771234567'],
            'status'                 => 'SUCCESSFUL',
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('PAYMENT_SUCCESSFUL', $event->getEventType());
        $this->assertEquals('mtnmomo', $event->getGateway());
        $this->assertEquals('ORDER_001', $event->getData()['reference']);
    }

    public function test_parse_failed_collection_payment(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'externalId' => 'ORDER_002',
            'amount'     => '500',
            'currency'   => 'UGX',
            'payer'      => ['partyIdType' => 'MSISDN', 'partyId' => '256771234567'],
            'status'     => 'FAILED',
            'reason'     => ['message' => 'Insufficient balance'],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('PAYMENT_FAILED', $event->getEventType());
    }

    public function test_parse_pending_payment(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'externalId' => 'ORDER_003',
            'amount'     => '500',
            'currency'   => 'UGX',
            'status'     => 'PENDING',
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('PAYMENT_PENDING', $event->getEventType());
    }

    public function test_parse_disbursement_transfer(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'financialTransactionId' => 'FIN_002',
            'externalId'             => 'TRANSFER_001',
            'amount'                 => '2000',
            'currency'               => 'UGX',
            'payee'                  => ['partyIdType' => 'MSISDN', 'partyId' => '256779876543'],
            'status'                 => 'SUCCESSFUL',
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('TRANSFER_SUCCESSFUL', $event->getEventType());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }
}
