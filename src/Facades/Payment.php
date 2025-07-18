<?php

namespace NexusPay\PaymentMadeEasy\Facades;

use Illuminate\Support\Facades\Facade;

class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'payment-gateways';
    }
}
