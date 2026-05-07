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
    public function createFlutterwaveDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.flutterwave');
        return $this->buildProvider(Drivers\FlutterwaveDriver::class, $config);
    }

    public function createStripeDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.stripe');
        return $this->buildProvider(Drivers\StripeDriver::class, $config);
    }

    public function createSeerbitDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.seerbit');
        return $this->buildProvider(Drivers\SeerbitDriver::class, $config);
    }

    public function createMonnifyDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.monnify');
        return $this->buildProvider(Drivers\MonnifyDriver::class, $config);
    }

    public function createSquadDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.squad');
        return $this->buildProvider(Drivers\SquadDriver::class, $config);
    }

    public function createRemitaDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.remita');
        return $this->buildProvider(Drivers\RemitaDriver::class, $config);
    }

    public function createBudpayDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.budpay');
        return $this->buildProvider(Drivers\BudpayDriver::class, $config);
    }

    public function createInterswitchDriver()
    {
        $config = $this->config->get('payment-gateways.gateways.interswitch');
        return $this->buildProvider(Drivers\InterswitchDriver::class, $config);
    }

    protected function buildProvider($provider, $config)
    {
        return new $provider($config);
    }
}
