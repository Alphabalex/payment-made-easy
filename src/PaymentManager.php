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

    /**
     * @param  string  $driver
     * @return mixed
     */
    protected function createDriver($driver)
    {
        $class = GatewayRegistry::driverClass($driver);
        if ($class === null) {
            throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
        }

        $config = $this->config->get('payment-gateways.gateways.' . $driver, []);

        return $this->buildProvider($class, $config);
    }

    /**
     * @param  class-string  $provider
     * @return mixed
     */
    protected function buildProvider($provider, array $config)
    {
        return new $provider($config);
    }
}
