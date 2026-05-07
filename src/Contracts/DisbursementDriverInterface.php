<?php

namespace NexusPay\PaymentMadeEasy\Contracts;

interface DisbursementDriverInterface
{
    /**
     * Initiate a single transfer to a bank account or mobile money wallet.
     *
     * Expected $data keys:
     *   amount          (float)  required  — amount in major currency unit
     *   currency        (string) required  — ISO 4217 currency code
     *   recipient_code  (string) required  — gateway recipient/beneficiary code from createTransferRecipient()
     *   reference       (string) optional  — unique caller-supplied reference
     *   reason          (string) optional  — narration / description shown on statement
     *
     * @param  array $data
     * @return array  Normalized response with 'transfer_code' and 'status' in data
     */
    public function transfer(array $data): array;

    /**
     * Initiate multiple transfers in a single API call.
     *
     * Each item in $transfers follows the same shape as transfer() $data,
     * but recipient_code is required for every item.
     *
     * @param  array $transfers  Array of transfer data arrays
     * @return array
     */
    public function bulkTransfer(array $transfers): array;

    /**
     * Verify / query the status of an existing transfer.
     *
     * @param  string $reference  Caller-supplied or gateway-issued transfer reference
     * @return array
     */
    public function verifyTransfer(string $reference): array;

    /**
     * List transfers with optional filters.
     *
     * @param  array $params  Optional filter/pagination params (e.g. page, per_page, status)
     * @return array
     */
    public function listTransfers(array $params = []): array;

    /**
     * Create a reusable transfer recipient (beneficiary).
     *
     * Expected $data keys:
     *   type            (string) required  — 'bank_account' | 'mobile_money' | 'nuban' etc.
     *   name            (string) required  — account/recipient name
     *   account_number  (string) required  — bank account number or mobile number
     *   bank_code       (string) required for bank transfers
     *   currency        (string) optional  — defaults to gateway default
     *   description     (string) optional
     *
     * @param  array $data
     * @return array  Normalized response with 'recipient_code' in data
     */
    public function createTransferRecipient(array $data): array;

    /**
     * List supported banks for the given country.
     *
     * @param  array $filters  Filters e.g. ['country' => 'NG', 'per_page' => 50]
     * @return array  Array of banks with 'name', 'code', and 'country'
     */
    public function listBanks(array $filters = []): array;

    /**
     * Resolve / validate a bank account number to get the account holder name.
     *
     * @param  array $data  e.g. ['account_number' => '...', 'bank_code' => '...']
     * @return array  Normalized response with 'account_name' and 'account_number' in data
     */
    public function resolveAccountNumber(array $data): array;
}
