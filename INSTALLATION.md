# Installation Instructions

## 1. Install via Composer
composer require kudos/payment-made-easy

## 2. Publish Configuration
php artisan vendor:publish --tag=payment-gateways-config

## 3. Environment Variables
Add the following to your .env file:

# Default Gateway
PAYMENT_GATEWAY=paystack
PAYMENT_CURRENCY=NGN

# Paystack
PAYSTACK_PUBLIC_KEY=your_paystack_public_key
PAYSTACK_SECRET_KEY=your_paystack_secret_key
PAYSTACK_CALLBACK_URL=https://yoursite.com/payment/callback

## 4. Usage
The package is now ready to use! See the usage examples for implementation details.
\`\`\`

This Laravel package provides:

1. **Driver-based architecture** - Easy to extend with new payment gateways
2. **Unified API** - Same methods across all drivers
3. **Configuration management** - Environment-based configuration
4. **Error handling** - Custom exceptions for payment errors
5. **Facade support** - Easy-to-use facade for quick access
6. **Dependency injection** - Full Laravel container integration

The package supports all major operations: payment initialization, verification, refunds, and transaction listing across all payment gateways.

