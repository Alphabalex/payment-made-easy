<?php

namespace NexusPay\PaymentMadeEasy;

use Illuminate\Support\Manager;
use InvalidArgumentException;

class PaymentManager extends Manager
{
    public function getDefaultDriver()
    {
        return $this->config->get('payment-gateways.default', 'paystack');
    }

    public function createPaystackDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.paystack');
        return $this->buildProvider(Drivers\PaystackDriver::class, $config);
    }

    protected function buildProvider($provider, $config)
    {
        return new $provider($config);
    }
}
