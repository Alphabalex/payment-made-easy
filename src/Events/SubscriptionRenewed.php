<?php

namespace NexusPay\PaymentMadeEasy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class SubscriptionRenewed
{
    use Dispatchable, SerializesModels;

    public WebhookEventInterface $webhookEvent;
    public array $subscriptionData;
    public array $invoiceData;

    public function __construct(WebhookEventInterface $webhookEvent, array $subscriptionData, array $invoiceData = [])
    {
        $this->webhookEvent = $webhookEvent;
        $this->subscriptionData = $subscriptionData;
        $this->invoiceData = $invoiceData;
    }
}
