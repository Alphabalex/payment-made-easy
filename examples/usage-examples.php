<?php

use NexusPay\PaymentMadeEasy\Facades\Payment;
use NexusPay\PaymentMadeEasy\PaymentManager;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;

// =============================================================================
// ONE-TIME PAYMENTS
// =============================================================================

// Initialize with the default driver (set via PAYMENT_GATEWAY env)
$response = Payment::initializePayment([
    'email'        => 'customer@example.com',
    'amount'       => 5000.00,   // always in major currency units (NGN, USD, etc.)
    'reference'    => 'ORDER_123',
    'callback_url' => 'https://yoursite.com/payment/callback',
    'metadata'     => ['order_id' => '123', 'customer_id' => '456'],
]);

// Use a specific driver
$paystackResponse = Payment::driver('paystack')->initializePayment([
    'email'  => 'customer@example.com',
    'amount' => 5000.00,
]);

$flutterwaveResponse = Payment::driver('flutterwave')->initializePayment([
    'email'    => 'customer@example.com',
    'amount'   => 5000.00,
    'name'     => 'John Doe',
    'phone'    => '+2348123456789',
    'currency' => 'NGN',
]);

$stripeResponse = Payment::driver('stripe')->initializePayment([
    'email'    => 'customer@example.com',
    'amount'   => 50.00,
    'currency' => 'usd',
]);

$seerbitResponse = Payment::driver('seerbit')->initializePayment([
    'email'    => 'customer@example.com',
    'amount'   => 5000.00,
    'currency' => 'NGN',
    'name'     => 'John Doe',
    'phone'    => '+2348123456789',
]);

// Verify payment after callback redirect
$verification = Payment::driver('paystack')->verifyPayment('ORDER_123');

// Get a specific payment
$payment = Payment::driver('paystack')->getPayment('ORDER_123');

// Refunds
$partialRefund = Payment::driver('paystack')->refundPayment('ORDER_123', 2500.00);
$fullRefund    = Payment::driver('paystack')->refundPayment('ORDER_123');

// List transactions
$transactions = Payment::driver('paystack')->getTransactions(['per_page' => 50, 'page' => 1]);

// =============================================================================
// SUBSCRIPTIONS & PLANS
// Available on: Paystack, Flutterwave, Stripe, Seerbit
// =============================================================================

$driver = Payment::driver('paystack');

// Plans
$plan     = $driver->createPlan([
    'name'     => 'Pro Monthly',
    'amount'   => 5000.00,
    'interval' => 'monthly',   // monthly | weekly | annually | quarterly
]);
$planCode = $plan['data']['plan_code'];

$driver->updatePlan($planCode, ['name' => 'Pro Monthly v2', 'amount' => 6000.00]);
$driver->getPlan($planCode);
$driver->listPlans(['per_page' => 20]);
$driver->deletePlan($planCode);

// Subscriptions
$subscription = $driver->createSubscription([
    'email'      => 'customer@example.com',
    'plan_code'  => $planCode,
    'start_date' => '2026-06-01T00:00:00.000Z',
]);
$subCode = $subscription['data']['subscription_code'];

$driver->getSubscription($subCode);
$driver->listSubscriptions(['plan' => $planCode]);
$driver->listCustomerSubscriptions('customer@example.com');
$driver->pauseSubscription($subCode);
$driver->resumeSubscription($subCode);
$driver->cancelSubscription($subCode);

// Stripe — plans map to Product + Price; interval options: day|week|month|year
$stripePlan = Payment::driver('stripe')->createPlan([
    'name'     => 'Pro Monthly',
    'amount'   => 29.99,
    'currency' => 'usd',
    'interval' => 'month',
]);
$priceId = $stripePlan['data']['plan_code'];   // Stripe Price ID

Payment::driver('stripe')->createSubscription([
    'email'     => 'customer@example.com',
    'plan_code' => $priceId,
]);

// =============================================================================
// DISBURSEMENTS & TRANSFERS
// Available on: Paystack, Flutterwave
// =============================================================================

$pDriver = Payment::driver('paystack');

// List supported banks
$banks = $pDriver->listBanks(['country' => 'nigeria']);

// Resolve an account number before sending money
$resolved = $pDriver->resolveAccountNumber([
    'account_number' => '0123456789',
    'bank_code'      => '044',   // Access Bank
]);

// Create a transfer recipient
$recipient = $pDriver->createTransferRecipient([
    'name'           => 'Jane Doe',
    'account_number' => '0123456789',
    'bank_code'      => '044',
    'currency'       => 'NGN',
]);
$recipientCode = $recipient['data']['recipient_code'];

// Single transfer
$transfer = $pDriver->transfer([
    'amount'    => 10000.00,
    'recipient' => $recipientCode,
    'reason'    => 'Salary payout',
    'reference' => 'PAYOUT_001',
]);

// Bulk transfer
$pDriver->bulkTransfer([
    'transfers' => [
        ['amount' => 5000.00, 'recipient' => 'RCP_abc', 'reference' => 'B001'],
        ['amount' => 3000.00, 'recipient' => 'RCP_def', 'reference' => 'B002'],
    ],
]);

// Verify & list transfers
$pDriver->verifyTransfer('PAYOUT_001');
$pDriver->listTransfers(['per_page' => 50]);

// =============================================================================
// VIRTUAL ACCOUNTS
// Available on: Paystack, Flutterwave, Seerbit
// =============================================================================

// Paystack — Dedicated Nuban
$va = Payment::driver('paystack')->createVirtualAccount([
    'email'          => 'customer@example.com',
    'name'           => 'Jane Doe',
    'bvn'            => '12345678901',
    'preferred_bank' => 'wema-bank',   // or 'titan-paystack'
]);
$accountId = $va['data']['id'];

Payment::driver('paystack')->getVirtualAccount($accountId);
Payment::driver('paystack')->listVirtualAccounts(['active' => true]);
Payment::driver('paystack')->deactivateVirtualAccount($accountId);

// Flutterwave — Virtual Account Numbers
$flwVa = Payment::driver('flutterwave')->createVirtualAccount([
    'email'        => 'customer@example.com',
    'is_permanent' => true,
    'bvn'          => '12345678901',
    'description'  => 'Jane Doe',
    'currency'     => 'NGN',
    'reference'    => 'VA_ORDER_001',
]);

// =============================================================================
// PAYMENT LINKS
// Available on: Paystack, Flutterwave, Stripe, Seerbit
// =============================================================================

$link = Payment::driver('paystack')->createPaymentLink([
    'name'        => 'Product Launch Special',
    'amount'      => 2500.00,
    'description' => 'Early-bird ticket',
    'currency'    => 'NGN',
]);
$linkId = $link['data']['id'];
$url    = $link['data']['link'];

Payment::driver('paystack')->updatePaymentLink($linkId, ['amount' => 3000.00]);
Payment::driver('paystack')->getPaymentLink($linkId);
Payment::driver('paystack')->listPaymentLinks();
Payment::driver('paystack')->disablePaymentLink($linkId);

// =============================================================================
// CAPABILITY DETECTION
// =============================================================================

$driver = Payment::driver('paystack');

if ($driver instanceof SubscriptionDriverInterface) {
    $driver->createPlan(['name' => 'Basic', 'amount' => 1000.00, 'interval' => 'monthly']);
}

if ($driver instanceof DisbursementDriverInterface) {
    $driver->listBanks();
}

if ($driver instanceof VirtualAccountDriverInterface) {
    $driver->createVirtualAccount(['email' => 'test@example.com', 'name' => 'Test User']);
}

if ($driver instanceof PaymentLinkDriverInterface) {
    $driver->createPaymentLink(['name' => 'Test Link', 'amount' => 500.00]);
}

// =============================================================================
// DEPENDENCY INJECTION
// =============================================================================

class PaymentController extends Controller
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function charge(Request $request)
    {
        $driver = $this->paymentManager->driver($request->gateway ?? 'paystack');

        return $driver->initializePayment([
            'email'     => $request->email,
            'amount'    => $request->amount,
            'reference' => $request->reference,
        ]);
    }

    public function createPlan(Request $request)
    {
        $driver = $this->paymentManager->driver($request->gateway ?? 'paystack');

        if (!$driver instanceof SubscriptionDriverInterface) {
            abort(422, 'Selected gateway does not support subscriptions.');
        }

        return $driver->createPlan($request->validated());
    }
}
