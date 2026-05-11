<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use NexusPay\PaymentMadeEasy\Drivers\PaystackDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class AbstractPaymentDriverConversionTest extends TestCase
{
    public function test_convert_amount_uses_minor_units(): void
    {
        $driver = new PaystackDriver([
            'base_url'   => 'https://api.paystack.co',
            'secret_key' => 'sk_test',
        ]);

        $method = new \ReflectionMethod(PaystackDriver::class, 'convertAmount');
        $method->setAccessible(true);

        $this->assertSame(1000, $method->invoke($driver, 10.0));
        $this->assertSame(1999, $method->invoke($driver, 19.99));
    }
}
