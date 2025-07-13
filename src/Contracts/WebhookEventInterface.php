<?php

namespace NexusPay\PaymentMadeEasy\Contracts;

interface WebhookEventInterface
{
    /**
     * Get the event type
     */
    public function getEventType(): string;

    /**
     * Get the event data
     */
    public function getData(): array;

    /**
     * Get the gateway that triggered the event
     */
    public function getGateway(): string;

    /**
     * Get the raw payload
     */
    public function getRawPayload(): array;

    /**
     * Get the event timestamp
     */
    public function getTimestamp(): \DateTime;

    /**
     * Check if the event is valid
     */
    public function isValid(): bool;
}
