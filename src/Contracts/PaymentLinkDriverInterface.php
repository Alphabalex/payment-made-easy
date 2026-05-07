<?php

namespace NexusPay\PaymentMadeEasy\Contracts;

interface PaymentLinkDriverInterface
{
    /**
     * Create a shareable payment link.
     *
     * Expected $data keys:
     *   amount       (float)  optional  — fixed amount; omit for customer-specified amount
     *   currency     (string) required
     *   name         (string) required  — link title / product name
     *   description  (string) optional
     *   callback_url (string) optional  — redirect URL after payment
     *   reference    (string) optional  — unique caller-supplied reference
     *   expires_at   (string) optional  — ISO 8601 datetime for link expiry
     *   metadata     (array)  optional
     *
     * @param  array $data
     * @return array  Normalized response with 'link_id' and 'url' in data
     */
    public function createPaymentLink(array $data): array;

    /**
     * Update an existing payment link.
     *
     * @param  string $linkId  Gateway-specific link identifier
     * @param  array  $data    Fields to update (same keys as createPaymentLink)
     * @return array
     */
    public function updatePaymentLink(string $linkId, array $data): array;

    /**
     * Retrieve details of a single payment link.
     *
     * @param  string $linkId
     * @return array
     */
    public function getPaymentLink(string $linkId): array;

    /**
     * List all payment links with optional filters.
     *
     * @param  array $params  Optional filter/pagination params (e.g. page, per_page, status)
     * @return array
     */
    public function listPaymentLinks(array $params = []): array;

    /**
     * Disable / deactivate a payment link so it can no longer be used.
     *
     * @param  string $linkId
     * @return array
     */
    public function disablePaymentLink(string $linkId): array;
}
