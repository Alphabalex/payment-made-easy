<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class TransferFailed
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $transferData;
    public string $reason;

    public function __construct(WebhookEventInterface $webhookEvent, array $transferData, string $reason = '')
    {
        $this->webhookEvent = $webhookEvent;
        $this->transferData = $transferData;
        $this->reason = $reason;
    }
}
