<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class ChargebackCreated
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $chargebackData;

    public function __construct(WebhookEventInterface $webhookEvent, array $chargebackData)
    {
        $this->webhookEvent = $webhookEvent;
        $this->chargebackData = $chargebackData;
    }
}
