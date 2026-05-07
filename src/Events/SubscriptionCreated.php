<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $subscriptionData;

    public function __construct(WebhookEventInterface $webhookEvent, array $subscriptionData)
    {
        $this->webhookEvent = $webhookEvent;
        $this->subscriptionData = $subscriptionData;
    }
}
