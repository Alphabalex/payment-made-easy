<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Support;

use NexusPay\PaymentMadeEasy\Support\DefaultWebhookLogSanitizer;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class DefaultWebhookLogSanitizerTest extends TestCase
{
    public function test_redacts_nested_sensitive_keys(): void
    {
        $sanitizer = new DefaultWebhookLogSanitizer();

        $out = $sanitizer->sanitize([
            'event' => 'charge.success',
            'data'  => [
                'reference' => 'ORD_1',
                'card'      => ['number' => '4111111111111111'],
                'customer'  => ['email' => 'a@b.com', 'secret_key' => 'sk_live_xx'],
            ],
        ]);

        $this->assertSame('[REDACTED]', $out['data']['card']);
        $this->assertSame('[REDACTED]', $out['data']['customer']['secret_key']);
        $this->assertSame('a@b.com', $out['data']['customer']['email']);
        $this->assertSame('ORD_1', $out['data']['reference']);
    }

    public function test_redacts_keys_matching_secret_like_suffixes(): void
    {
        $sanitizer = new DefaultWebhookLogSanitizer();

        $out = $sanitizer->sanitize([
            'event'            => 'charge.success',
            'gateway_secret'   => 'should-redact',
            'metadata'         => ['my_api_key' => 'also-redact', 'invoice_id' => 'INV-1'],
        ]);

        $this->assertSame('[REDACTED]', $out['gateway_secret']);
        $this->assertSame('[REDACTED]', $out['metadata']['my_api_key']);
        $this->assertSame('INV-1', $out['metadata']['invoice_id']);
    }
}
