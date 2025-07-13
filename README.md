# Payment Made Easy

A comprehensive Laravel package for integrating multiple payment gateways with webhook support.

## Supported Payment Gateways

- **Paystack** - Nigerian payment gateway

# Installation Instructions

## 1. Install via Composer

```bash
composer require nexuspay/payment-made-easy
```

## 2. Publish Configuration

```bash
php artisan vendor:publish --provider="NexusPay\PaymentMadeEasy\PaymentServiceProvider"
```

## 3. Environment Variables

Add the following to your .env file:

```env
# Default Gateway
PAYMENT_GATEWAY=paystack
PAYMENT_CURRENCY=NGN

# Paystack
PAYSTACK_PUBLIC_KEY=your_paystack_public_key
PAYSTACK_SECRET_KEY=your_paystack_secret_key
PAYSTACK_CALLBACK_URL=https://yoursite.com/payment/callback
```

## 4. Usage

The package is now ready to use! See the usage examples for implementation details.

## Basic Usage

```php
use NexusPay\PaymentMadeEasy\Facades\Payment;

// Initialize payment
$response = Payment::driver('paystack')->initializePayment([
    'email' => 'customer@example.com',
    'amount' => 1000, // Amount in kobo/cents
    'reference' => 'ORDER_123',
    'callback_url' => 'https://yoursite.com/payment/callback',
]);

// Verify payment
$verification = Payment::driver('paystack')->verifyPayment('ORDER_123');

// Process refund
$refund = Payment::driver('paystack')->refundPayment('ORDER_123', 500);
```

## Webhook Support

The package includes comprehensive webhook support for all gateways:

```php
// Webhook URLs are automatically registered:
// POST /webhooks/payment-gateways/{gateway}

// Example: https://yoursite.com/webhooks/payment-gateways/paystack
```

## Advanced Features

- **Driver Pattern**: Easy to extend with new payment gateways
- **Event System**: Laravel events for payment notifications
- **Webhook Verification**: Automatic signature verification
- **Exception Handling**: Comprehensive error handling
- **Multi-Currency**: Support for multiple currencies
- **Subscription Support**: Recurring payment support where available

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email babusunnah@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
