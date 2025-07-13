<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class PaymentSuccessful
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $paymentData;

    public function __construct(WebhookEventInterface $webhookEvent, array $paymentData)
    {
        $this->webhookEvent = $webhookEvent;
        $this->paymentData = $paymentData;
    }
}
