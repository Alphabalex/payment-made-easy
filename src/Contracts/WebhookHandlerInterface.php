<?php

namespace NexusPay\PaymentMadeEasy\Contracts;

use Illuminate\Http\Request;

interface WebhookHandlerInterface
{
    /**
     * Verify the webhook signature
     */
    public function verifySignature(Request $request): bool;

    /**
     * Parse the webhook payload
     */
    public function parsePayload(Request $request): WebhookEventInterface;

    /**
     * Handle the webhook event
     */
    public function handle(WebhookEventInterface $event): void;
}
