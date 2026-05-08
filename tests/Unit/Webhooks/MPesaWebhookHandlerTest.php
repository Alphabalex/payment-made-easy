<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\MPesaWebhookHandler;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MPesaWebhookHandlerTest extends TestCase
{
    private function makeHandler(): MPesaWebhookHandler
    {
        return new MPesaWebhookHandler([], 'mpesa');
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
        // M-Pesa uses IP whitelisting, not HMAC
        $handler = $this->makeHandler();
        $request = $this->makeRequest(['Body' => ['stkCallback' => ['MerchantRequestID' => '1234']]]);

        $this->assertTrue($handler->verifySignature($request));
    }

    public function test_parse_stk_push_success_payload(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID'   => 'MR_001',
                    'CheckoutRequestID'   => 'CR_001',
                    'ResultCode'          => 0,
                    'ResultDesc'          => 'The service request is processed successfully.',
                    'CallbackMetadata'    => [
                        'Item' => [
                            ['Name' => 'Amount',             'Value' => 1000],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QGH123456'],
                            ['Name' => 'PhoneNumber',        'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertInstanceOf(WebhookEventInterface::class, $event);
        $this->assertEquals('STK_PUSH_SUCCESS', $event->getEventType());
        $this->assertEquals('mpesa', $event->getGateway());
        $this->assertEquals('QGH123456', $event->getData()['transaction_id']);
    }

    public function test_parse_stk_push_failed_payload(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'CR_002',
                    'ResultCode'        => 1032,
                    'ResultDesc'        => 'Request cancelled by user.',
                    'CallbackMetadata'  => ['Item' => []],
                ],
            ],
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('STK_PUSH_FAILED', $event->getEventType());
    }

    public function test_parse_c2b_payment_payload(): void
    {
        $handler = $this->makeHandler();
        $payload = [
            'TransactionType'   => 'Pay Bill',
            'TransID'           => 'TXN_001',
            'TransAmount'       => 1000,
            'MSISDN'            => '254712345678',
            'BillRefNumber'     => 'ORDER_001',
        ];

        $event = $handler->parsePayload($this->makeRequest($payload));

        $this->assertEquals('C2B_PAYMENT', $event->getEventType());
    }

    public function test_parse_payload_throws_on_invalid_json(): void
    {
        $handler = $this->makeHandler();
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{bad}');

        $this->expectException(WebhookException::class);
        $handler->parsePayload($request);
    }
}
