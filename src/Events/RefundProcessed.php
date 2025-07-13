<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class RefundProcessed
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $refundData;

    public function __construct(WebhookEventInterface $webhookEvent, array $refundData)
    {
        $this->webhookEvent = $webhookEvent;
        $this->refundData = $refundData;
    }
}
