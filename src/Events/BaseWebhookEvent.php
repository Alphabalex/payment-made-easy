<?php

namespace NexusPay\PaymentMadeEasy\Events;

use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;

class BaseWebhookEvent implements WebhookEventInterface
{
    protected string $eventType;
    protected array $data;
    protected string $gateway;
    protected array $rawPayload;
    protected \DateTime $timestamp;

    public function __construct(
        string $eventType,
        array $data,
        string $gateway,
        array $rawPayload,
        ?\DateTime $timestamp = null
    ) {
        $this->eventType = $eventType;
        $this->data = $data;
        $this->gateway = $gateway;
        $this->rawPayload = $rawPayload;
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }

    public function isValid(): bool
    {
        return !empty($this->eventType) && !empty($this->data);
    }
}
