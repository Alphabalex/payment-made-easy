<?php

namespace NexusPay\PaymentMadeEasy\Contracts;

interface VirtualAccountDriverInterface
{
    /**
     * Create a virtual/dedicated bank account for a customer.
     *
     * Virtual accounts allow customers to make bank transfers at any time;
     * the gateway credits the merchant when a transfer is received.
     *
     * Expected $data keys:
     *   email           (string) required  — customer email
     *   name            (string) required  — customer full name
     *   reference       (string) optional  — unique caller-supplied reference
     *   preferred_bank  (string) optional  — preferred bank for the virtual account
     *   bvn             (string) optional  — required by some Nigerian gateways for compliance
     *   description     (string) optional
     *   metadata        (array)  optional
     *
     * @param  array $data
     * @return array  Normalized response with 'account_number', 'bank_name', 'account_name' in data
     */
    public function createVirtualAccount(array $data): array;

    /**
     * Retrieve an existing virtual account by its reference.
     *
     * @param  string $reference  Caller-supplied or gateway-issued account reference
     * @return array
     */
    public function getVirtualAccount(string $reference): array;

    /**
     * List all virtual accounts with optional filters.
     *
     * @param  array $params  Optional filter/pagination params (e.g. page, per_page, status)
     * @return array
     */
    public function listVirtualAccounts(array $params = []): array;

    /**
     * Deactivate / close a virtual account so it no longer accepts credits.
     *
     * @param  string $reference
     * @return array
     */
    public function deactivateVirtualAccount(string $reference): array;
}
