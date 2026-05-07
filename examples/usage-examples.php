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

// ---------------------------------------------------------------------------
// Monnify
$monnifyResponse = Payment::driver('monnify')->initializePayment([
    'email'       => 'customer@example.com',
    'amount'      => 5000.00,
    'currency'    => 'NGN',
    'name'        => 'John Doe',
    'description' => 'Order #123',
    'reference'   => 'ORDER_123',
]);
// Redirect to $monnifyResponse['data']['checkoutUrl']

// ---------------------------------------------------------------------------
// Squad (GTCo)
$squadResponse = Payment::driver('squad')->initializePayment([
    'email'     => 'customer@example.com',
    'amount'    => 5000.00,
    'currency'  => 'NGN',
    'reference' => 'SQ_' . uniqid(),
]);
// Redirect to $squadResponse['data']['authorization_url']

// ---------------------------------------------------------------------------
// Remita (RRR flow)
$remitaResponse = Payment::driver('remita')->initializePayment([
    'email'       => 'customer@example.com',
    'amount'      => 5000.00,
    'description' => 'Invoice #1001',
    'name'        => 'John Doe',
    'reference'   => 'RMT_1001',
]);
$rrr = $remitaResponse['data']['rrr'];
// Redirect to $remitaResponse['data']['authorization_url']
// After callback, verify using the RRR:
Payment::driver('remita')->verifyPayment($rrr);

// ---------------------------------------------------------------------------
// Budpay
$budpayResponse = Payment::driver('budpay')->initializePayment([
    'email'     => 'customer@example.com',
    'amount'    => 5000.00,
    'reference' => 'BDP_' . uniqid(),
    'currency'  => 'NGN',
]);

// ---------------------------------------------------------------------------
// Interswitch (Webpay)
$interswitchResponse = Payment::driver('interswitch')->initializePayment([
    'email'     => 'customer@example.com',
    'amount'    => 5000.00,
    'currency'  => 'NGN',
    'reference' => 'ISW_' . uniqid(),
]);
// Redirect to $interswitchResponse['data']['authorization_url']

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
// Available on: Paystack, Flutterwave, Monnify, Squad, Remita, Budpay
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

// ---------------------------------------------------------------------------
// Monnify Disbursements
$mDriver = Payment::driver('monnify');

$mDriver->resolveAccountNumber([
    'account_number' => '0123456789',
    'bank_code'      => '044',
]);

$mDriver->transfer([
    'amount'              => 10000.00,
    'account_number'      => '0123456789',
    'bank_code'           => '044',
    'narration'           => 'Salary payout',
    'reference'           => 'MNFY_PAYOUT_001',
    'wallet_account_number' => config('payment-gateways.gateways.monnify.wallet_account_number'),
]);

$mDriver->bulkTransfer([
    'transfers' => [
        ['amount' => 5000.00, 'account_number' => '0123456789', 'bank_code' => '044', 'reference' => 'B001'],
        ['amount' => 3000.00, 'account_number' => '9876543210', 'bank_code' => '058', 'reference' => 'B002'],
    ],
]);

$mDriver->verifyTransfer('MNFY_PAYOUT_001');
$mDriver->listTransfers(['pageSize' => 20, 'pageNo' => 0]);
$mDriver->listBanks();

// ---------------------------------------------------------------------------
// Squad Disbursements
$sqDriver = Payment::driver('squad');

$sqDriver->transfer([
    'account_number' => '0123456789',
    'bank_code'      => '044',
    'amount'         => 5000.00,
    'currency'       => 'NGN',
    'reference'      => 'SQ_PAYOUT_001',
    'narration'      => 'Salary',
]);

$sqDriver->listBanks();

// ---------------------------------------------------------------------------
// Remita Bulk Disbursement
$rmDriver = Payment::driver('remita');

$rmDriver->bulkTransfer([
    'batchRef'    => 'BATCH_001',
    'narration'   => 'Payroll',
    'transfers'   => [
        ['amount' => 5000.00, 'account_number' => '0123456789', 'bank_code' => '044', 'name' => 'Jane Doe'],
    ],
]);

// ---------------------------------------------------------------------------
// Budpay Disbursements
$bdDriver = Payment::driver('budpay');

$bdDriver->transfer([
    'amount'         => 5000.00,
    'currency'       => 'NGN',
    'account_number' => '0123456789',
    'bank_code'      => '044',
    'narration'      => 'Payout',
    'reference'      => 'BDP_PAYOUT_001',
]);

$bdDriver->bulkTransfer([
    'transfers' => [
        ['amount' => 5000.00, 'currency' => 'NGN', 'account_number' => '0123456789', 'bank_code' => '044', 'reference' => 'B001'],
    ],
]);

// =============================================================================
// VIRTUAL ACCOUNTS
// Available on: Paystack, Flutterwave, Seerbit, Monnify, Squad, Budpay
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

// ---------------------------------------------------------------------------
// Monnify — Reserved Account (core Monnify feature)
$monnifyVa = Payment::driver('monnify')->createVirtualAccount([
    'email'             => 'customer@example.com',
    'name'              => 'Jane Doe',
    'bvn'               => '12345678901',
    'currency_code'     => 'NGN',
    'contract_code'     => config('payment-gateways.gateways.monnify.contract_code'),
    'reference'         => 'VA_' . uniqid(),
    'split_percentages' => [],
]);
$reservedAccountRef = $monnifyVa['data']['accountReference'];

Payment::driver('monnify')->getVirtualAccount($reservedAccountRef);
Payment::driver('monnify')->deactivateVirtualAccount($reservedAccountRef);

// ---------------------------------------------------------------------------
// Squad — Virtual Account
$squadVa = Payment::driver('squad')->createVirtualAccount([
    'customer_identifier' => 'customer_001',
    'email'               => 'customer@example.com',
    'name'                => 'Jane Doe',
    'mobile_num'          => '08123456789',
]);

Payment::driver('squad')->getVirtualAccount($squadVa['data']['virtual_account_number']);

// ---------------------------------------------------------------------------
// Budpay — Virtual Account
$budpayVa = Payment::driver('budpay')->createVirtualAccount([
    'email'      => 'customer@example.com',
    'amount'     => 5000.00,
    'currency'   => 'NGN',
    'name'       => 'Jane Doe',
    'reference'  => 'BDP_VA_' . uniqid(),
]);

Payment::driver('budpay')->getVirtualAccount($budpayVa['data']['id']);

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

// =============================================================================
// PHASE 4 — GLOBAL GATEWAYS
// =============================================================================

// ---------------------------------------------------------------------------
// PayPal — One-Time Payment
// ---------------------------------------------------------------------------

$paypalOrder = Payment::driver('paypal')->initializePayment([
    'amount'      => 49.99,
    'currency'    => 'USD',
    'email'       => 'buyer@example.com',
    'description' => 'Order #12345',
]);
// Redirect customer to the approve link:
// $paypalOrder['links'][n]['href'] where rel == 'approve'

// Capture after customer approves:
$captured = Payment::driver('paypal')->verifyPayment($paypalOrder['id']);

// Refund
Payment::driver('paypal')->refundPayment($captured['id'], 20.00); // partial
Payment::driver('paypal')->refundPayment($captured['id']);         // full

// ---------------------------------------------------------------------------
// PayPal — Subscriptions
// ---------------------------------------------------------------------------

$ppDriver = Payment::driver('paypal');

$plan = $ppDriver->createPlan([
    'name'       => 'Pro Monthly',
    'amount'     => 9.99,
    'currency'   => 'USD',
    'interval'   => 'monthly',
    // Optionally supply an existing PayPal product_id:
    // 'product_id' => 'PROD-XXXXXXXX',
]);
$planId = $plan['data']['plan_id'];

$subscription = $ppDriver->createSubscription([
    'email'     => 'buyer@example.com',
    'plan_code' => $planId,
]);
// Redirect to $subscription['links'][n]['href'] where rel == 'approve'

$ppDriver->cancelSubscription($subscription['id']);

// ---------------------------------------------------------------------------
// PayPal — Payouts (Disbursements)
// ---------------------------------------------------------------------------

Payment::driver('paypal')->transfer([
    'email'     => 'recipient@example.com',  // PayPal receiver email
    'amount'    => 100.00,
    'currency'  => 'USD',
    'narration' => 'Freelancer payment',
    'reference' => 'PAYOUT_PP_001',
]);

Payment::driver('paypal')->bulkTransfer([
    'transfers' => [
        ['email' => 'alice@example.com', 'amount' => 50.00, 'currency' => 'USD', 'reference' => 'B001'],
        ['email' => 'bob@example.com',   'amount' => 75.00, 'currency' => 'USD', 'reference' => 'B002'],
    ],
]);

// ---------------------------------------------------------------------------
// PayPal — Payment Links
// ---------------------------------------------------------------------------

$link = Payment::driver('paypal')->createPaymentLink([
    'amount'      => 25.00,
    'currency'    => 'USD',
    'description' => 'E-book purchase',
]);
Payment::driver('paypal')->disablePaymentLink($link['data']['id']);

// ---------------------------------------------------------------------------
// M-Pesa (Safaricom Kenya) — STK Push
// ---------------------------------------------------------------------------

// Initiate payment — sends prompt to customer's phone
$mpesaResponse = Payment::driver('mpesa')->initializePayment([
    'phone'     => '254712345678',   // MSISDN in international format (no +)
    'amount'    => 1000,             // KES, whole number
    'reference' => 'ORDER_001',
    'narration' => 'Payment for Order #001',
]);
$checkoutRequestId = $mpesaResponse['data']['checkout_request_id'];

// Poll for status (call this from your callback handler or a polling job)
$status = Payment::driver('mpesa')->verifyPayment($checkoutRequestId);

// Transaction status query
Payment::driver('mpesa')->getTransactions([
    'transaction_id' => 'QKA12BC345',
    'party_a'        => '254712345678',
    'identifier_type' => '1',
]);

// Refund / reversal
Payment::driver('mpesa')->refundPayment('QKA12BC345', 500);

// ---------------------------------------------------------------------------
// MTN MoMo — RequestToPay (Collections)
// ---------------------------------------------------------------------------

$momoResponse = Payment::driver('mtnmomo')->initializePayment([
    'phone'    => '256771234567',  // MSISDN (no +)
    'amount'   => 5000,
    'currency' => 'UGX',
    'narration' => 'Invoice #42',
]);
$momoRef = $momoResponse['data']['reference'];  // UUID sent as X-Reference-Id

// Poll status (async — no synchronous confirmation from MoMo)
$momoStatus = Payment::driver('mtnmomo')->verifyPayment($momoRef);

// Refund
Payment::driver('mtnmomo')->refundPayment($momoRef, 2500);

// ---------------------------------------------------------------------------
// MTN MoMo — Disbursements (Transfer)
// ---------------------------------------------------------------------------

$momoTransfer = Payment::driver('mtnmomo')->transfer([
    'phone'    => '256771234567',
    'amount'   => 10000,
    'currency' => 'UGX',
    'narration' => 'Salary payout',
    'reference' => 'MOMO_PAY_001',
]);

// Verify disbursement
Payment::driver('mtnmomo')->verifyTransfer($momoTransfer['data']['reference']);

// Account lookup
Payment::driver('mtnmomo')->resolveAccountNumber([
    'phone' => '256771234567',
]);

// ---------------------------------------------------------------------------
// Razorpay — One-Time Payment
// ---------------------------------------------------------------------------

$rzOrder = Payment::driver('razorpay')->initializePayment([
    'email'    => 'customer@example.com',
    'amount'   => 499.00,   // INR — converted to paise internally
    'currency' => 'INR',
    'name'     => 'Jane Doe',
    'phone'    => '+919876543210',
]);
// Use $rzOrder['id'], $rzOrder['data']['key_id'] with Razorpay checkout.js

// Verify order
Payment::driver('razorpay')->verifyPayment($rzOrder['id']);

// Get payment by Razorpay payment_id (from checkout callback)
Payment::driver('razorpay')->getPayment('pay_XXXXXXXX');

// Refund
Payment::driver('razorpay')->refundPayment('pay_XXXXXXXX', 100.00);

// ---------------------------------------------------------------------------
// Razorpay — Subscriptions
// ---------------------------------------------------------------------------

$rzDriver = Payment::driver('razorpay');

$rzPlan = $rzDriver->createPlan([
    'name'     => 'Basic Monthly',
    'amount'   => 999.00,
    'currency' => 'INR',
    'interval' => 'monthly',
]);
$rzPlanId = $rzPlan['data']['plan_code'];

$rzSub = $rzDriver->createSubscription([
    'email'       => 'customer@example.com',
    'plan_code'   => $rzPlanId,
    'total_count' => 12,   // 12 billing cycles
    'start_at'    => time() + 3600,
]);

$rzDriver->pauseSubscription($rzSub['id']);
$rzDriver->resumeSubscription($rzSub['id']);
$rzDriver->cancelSubscription($rzSub['id']);

// ---------------------------------------------------------------------------
// Razorpay — Disbursements (Payouts via Razorpay X)
// ---------------------------------------------------------------------------

// Step 1 — create a contact + fund account (one-time per recipient)
$contact = Payment::driver('razorpay')->createTransferRecipient([
    'name'           => 'Jane Doe',
    'account_number' => '9876543210',
    'bank_code'      => 'HDFC0001234',  // IFSC code
    'currency'       => 'INR',
]);
$fundAccountId = $contact['data']['fund_account_id'];

// Step 2 — send payout
Payment::driver('razorpay')->transfer([
    'fund_account_id' => $fundAccountId,
    'amount'          => 10000.00,
    'currency'        => 'INR',
    'narration'       => 'Freelancer payment',
    'reference'       => 'RZP_PAY_001',
]);

// ---------------------------------------------------------------------------
// Razorpay — Payment Links
// ---------------------------------------------------------------------------

$rzLink = Payment::driver('razorpay')->createPaymentLink([
    'amount'      => 1500.00,
    'currency'    => 'INR',
    'description' => 'Invoice #88',
    'email'       => 'customer@example.com',
    'phone'       => '+919876543210',
    'expire_by'   => time() + 86400,   // expires in 24 hours
]);
Payment::driver('razorpay')->disablePaymentLink($rzLink['data']['id']);

// ---------------------------------------------------------------------------
// Paddle — One-Time Transaction
// ---------------------------------------------------------------------------

$paddleTransaction = Payment::driver('paddle')->initializePayment([
    'amount'      => 29.99,
    'currency'    => 'USD',
    'description' => 'Pro Tier — Annual',
    'email'       => 'customer@example.com',
]);
// Redirect to $paddleTransaction['data']['authorization_url']

// Verify
Payment::driver('paddle')->verifyPayment($paddleTransaction['id']);

// Refund (creates an adjustment)
Payment::driver('paddle')->refundPayment($paddleTransaction['id']);        // full
Payment::driver('paddle')->refundPayment($paddleTransaction['id'], 10.00); // partial

// ---------------------------------------------------------------------------
// Paddle — Subscriptions
// ---------------------------------------------------------------------------

$pdDriver = Payment::driver('paddle');

// Create a plan (Paddle: creates a Product then a Price)
$pdPlan = $pdDriver->createPlan([
    'name'     => 'Growth Monthly',
    'amount'   => 49.00,
    'currency' => 'USD',
    'interval' => 'monthly',
    // Optionally supply existing Paddle product_id:
    // 'product_id' => 'pro_XXXXXXXX',
]);
$pdPriceId = $pdPlan['data']['plan_code'];

// Subscriptions are created via hosted checkout (returns checkout URL)
$pdSub = $pdDriver->createSubscription([
    'email'     => 'customer@example.com',
    'plan_code' => $pdPriceId,
]);
// Redirect to $pdSub['data']['authorization_url']

// Cancel at next billing period
$pdDriver->cancelSubscription($pdSub['id']);

// List customer subscriptions
$pdDriver->listCustomerSubscriptions('customer@example.com');

// Archive a price (soft-delete a plan)
$pdDriver->deletePlan($pdPriceId);

// ---------------------------------------------------------------------------
// Paddle — Payment Links
// ---------------------------------------------------------------------------

$pdLink = Payment::driver('paddle')->createPaymentLink([
    'amount'      => 19.99,
    'currency'    => 'USD',
    'description' => 'Starter pack',
]);
$pdLinkId = $pdLink['data']['id'];
Payment::driver('paddle')->disablePaymentLink($pdLinkId);
