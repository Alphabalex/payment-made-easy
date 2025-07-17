<?php

// Using the facade
use NexusPay\PaymentMadeEasy\PaymentManager;
use NexusPay\PaymentMadeEasy\Facades\Payment;

// Initialize payment with default driver (from config)
$response = Payment::initializePayment([
    'email' => 'customer@example.com',
    'amount' => 1000.00,
    'reference' => 'unique_reference_123',
    'callback_url' => 'https://yoursite.com/payment/callback',
    'metadata' => [
        'order_id' => '12345',
        'customer_id' => '67890',
    ],
]);

// Using specific driver
$paystackResponse = Payment::driver('paystack')->initializePayment([
    'email' => 'customer@example.com',
    'amount' => 1000.00,
]);

$flutterwaveResponse = Payment::driver('flutterwave')->initializePayment([
    'email' => 'customer@example.com',
    'amount' => 1000.00,
    'name' => 'John Doe',
    'phone' => '+2348123456789',
]);

$stripeResponse = Payment::driver('stripe')->initializePayment([
    'email' => 'customer@example.com',
    'amount' => 1000.00,
    'currency' => 'usd',
]);

// Verify payment
$verification = Payment::verifyPayment('payment_reference');

// Get payment details
$payment = Payment::getPayment('payment_reference');

// Refund payment
$refund = Payment::refundPayment('payment_reference', 500.00); // Partial refund
$fullRefund = Payment::refundPayment('payment_reference'); // Full refund

// Get transactions
$transactions = Payment::getTransactions([
    'per_page' => 50,
    'page' => 1,
]);

// Using dependency injection
class PaymentController extends Controller
{
    public function __construct(
        private PaymentManager $paymentManager
    ) {}

    public function initializePayment(Request $request)
    {
        $driver = $this->paymentManager->driver($request->gateway);

        return $driver->initializePayment([
            'email' => $request->email,
            'amount' => $request->amount,
            'reference' => $request->reference,
        ]);
    }
}
