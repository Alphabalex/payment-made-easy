<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class TransferSuccessful
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $transferData;

    public function __construct(WebhookEventInterface $webhookEvent, array $transferData)
    {
        $this->webhookEvent = $webhookEvent;
        $this->transferData = $transferData;
    }
}
