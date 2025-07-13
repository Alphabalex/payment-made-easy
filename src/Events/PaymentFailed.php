<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $paymentData;
    public string $reason;

    public function __construct(WebhookEventInterface $webhookEvent, array $paymentData, string $reason = '')
    {
        $this->webhookEvent = $webhookEvent;
        $this->paymentData = $paymentData;
        $this->reason = $reason;
    }
}
