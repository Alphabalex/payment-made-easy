<?php

namespace NexusPay\PaymentMadeEasy\Contracts;

interface SubscriptionDriverInterface
{
    /**
     * Create a subscription plan.
     *
     * Expected $data keys:
     *   name        (string)  required  — human-readable plan name
     *   amount      (float)   required  — amount in major currency unit (e.g. 5000.00 for NGN 5,000)
     *   currency    (string)  required  — ISO 4217 currency code (e.g. 'NGN', 'USD')
     *   interval    (string)  required  — one of: daily | weekly | monthly | quarterly | biannually | annually
     *   description (string)  optional
     *   trial_days  (int)     optional  — number of free trial days before first charge
     *   invoice_limit (int)   optional  — max number of charges; 0 = unlimited
     *
     * @param  array $data
     * @return array  Normalized response with 'plan_code' in data
     */
    public function createPlan(array $data): array;

    /**
     * Update an existing plan.
     *
     * @param  string $planCode  Gateway-specific plan identifier
     * @param  array  $data      Fields to update (same keys as createPlan)
     * @return array
     */
    public function updatePlan(string $planCode, array $data): array;

    /**
     * Retrieve a single plan.
     *
     * @param  string $planCode
     * @return array
     */
    public function getPlan(string $planCode): array;

    /**
     * List all plans.
     *
     * @param  array $params  Optional filter/pagination params (e.g. per_page, page)
     * @return array
     */
    public function listPlans(array $params = []): array;

    /**
     * Delete / deactivate a plan.
     *
     * @param  string $planCode
     * @return array
     */
    public function deletePlan(string $planCode): array;

    /**
     * Create a subscription for a customer.
     *
     * Expected $data keys:
     *   customer    (string)  required  — customer email or gateway customer code
     *   plan        (string)  required  — plan code returned by createPlan
     *   start_date  (string)  optional  — ISO 8601 date; defaults to immediately
     *   authorization (string) optional — stored authorization code (for gateways that require it)
     *
     * @param  array $data
     * @return array  Normalized response with 'subscription_code' in data
     */
    public function createSubscription(array $data): array;

    /**
     * Cancel a subscription (no further charges).
     *
     * @param  string $subscriptionCode
     * @return array
     */
    public function cancelSubscription(string $subscriptionCode): array;

    /**
     * Pause a subscription (suspend charges temporarily).
     *
     * @param  string $subscriptionCode
     * @return array
     */
    public function pauseSubscription(string $subscriptionCode): array;

    /**
     * Resume a previously paused subscription.
     *
     * @param  string $subscriptionCode
     * @return array
     */
    public function resumeSubscription(string $subscriptionCode): array;

    /**
     * Retrieve details of a single subscription.
     *
     * @param  string $subscriptionCode
     * @return array
     */
    public function getSubscription(string $subscriptionCode): array;

    /**
     * List all subscriptions.
     *
     * @param  array $params  Optional filter/pagination params
     * @return array
     */
    public function listSubscriptions(array $params = []): array;

    /**
     * List all subscriptions belonging to a specific customer.
     *
     * @param  string $customerEmail
     * @return array
     */
    public function listCustomerSubscriptions(string $customerEmail): array;
}
