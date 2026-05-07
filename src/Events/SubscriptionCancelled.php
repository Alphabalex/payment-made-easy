<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $subscriptionData;
    public string $reason;

    public function __construct(WebhookEventInterface $webhookEvent, array $subscriptionData, string $reason = '')
    {
        $this->webhookEvent = $webhookEvent;
        $this->subscriptionData = $subscriptionData;
        $this->reason = $reason;
    }
}
