<?php

namespace Kudos\PaymentMadeEasy\Contracts;

interface PaymentDriverInterface
{
    /**
     * Initialize a payment
     *
     * @param array $data
     * @return array
     */
    public function initializePayment(array $data): array;

    /**
     * Verify a payment
     *
     * @param string $reference
     * @return array
     */
    public function verifyPayment(string $reference): array;

    /**
     * Get payment details
     *
     * @param string $reference
     * @return array
     */
    public function getPayment(string $reference): array;

    /**
     * Refund a payment
     *
     * @param string $reference
     * @param float|null $amount
     * @return array
     */
    public function refundPayment(string $reference, ?float $amount = null): array;

    /**
     * Get all transactions
     *
     * @param array $params
     * @return array
     */
    public function getTransactions(array $params = []): array;
}