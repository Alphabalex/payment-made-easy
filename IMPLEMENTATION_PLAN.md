# Payment Made Easy — Implementation Plan

## Current State

### What Exists
| Area                         | Status    | Notes                                            |
| ---------------------------- | --------- | ------------------------------------------------ |
| One-time payment             | ✅ Partial | Paystack, Flutterwave, Stripe, Seerbit           |
| Refunds                      | ✅ Partial | All 4 drivers                                    |
| Webhooks                     | ✅ Partial | All 4 drivers                                    |
| Recurring / Subscriptions    | ❌ Missing | No contract, no drivers                          |
| Disbursements / Payouts      | ❌ Missing | Config has transfer events but no driver methods |
| Mobile Money                 | ❌ Missing | Critical for Africa                              |
| USSD Payments                | ❌ Missing | High value for Nigeria                           |
| Virtual Accounts             | ❌ Missing | Widely used in Nigeria                           |
| Bank Transfer (direct debit) | ❌ Missing |                                                  |
| Split Payments               | ❌ Missing |                                                  |
| Payment Links                | ❌ Partial | Only in SeerbitDriver as custom method           |
| Tests                        | ❌ Missing | No test files                                    |
| Database Migrations          | ❌ Missing | No local transaction storage                     |

### Interface Gap
`PaymentDriverInterface` only defines:
- `initializePayment` / `verifyPayment` / `getPayment` / `refundPayment` / `getTransactions`

Everything else (subscriptions, transfers, virtual accounts) is undefined at the contract level.

---

## Phase 1 — Strengthen the Contract Layer

### 1.1 Extend `PaymentDriverInterface`

Split the monolithic interface into composable contracts:

```
src/Contracts/
├── PaymentDriverInterface.php         (existing — one-time payment)
├── SubscriptionDriverInterface.php    (NEW)
├── DisbursementDriverInterface.php    (NEW)
├── VirtualAccountDriverInterface.php  (NEW)
├── PaymentLinkDriverInterface.php     (NEW)
├── WebhookEventInterface.php          (existing)
└── WebhookHandlerInterface.php        (existing)
```

**`SubscriptionDriverInterface`** methods:
```php
createPlan(array $data): array
updatePlan(string $planCode, array $data): array
getPlan(string $planCode): array
listPlans(array $params = []): array
deletePlan(string $planCode): array

createSubscription(array $data): array        // { customer, plan, start_date, ... }
cancelSubscription(string $subscriptionCode): array
pauseSubscription(string $subscriptionCode): array
resumeSubscription(string $subscriptionCode): array
getSubscription(string $subscriptionCode): array
listSubscriptions(array $params = []): array
listCustomerSubscriptions(string $customerEmail): array
```

**`DisbursementDriverInterface`** methods:
```php
transfer(array $data): array              // single transfer
bulkTransfer(array $transfers): array     // batch
verifyTransfer(string $reference): array
listTransfers(array $params = []): array
createTransferRecipient(array $data): array
listBanks(string $country = 'NG'): array
resolveAccountNumber(string $account, string $bankCode): array
```

**`VirtualAccountDriverInterface`** methods:
```php
createVirtualAccount(array $data): array
getVirtualAccount(string $reference): array
listVirtualAccounts(array $params = []): array
deactivateVirtualAccount(string $reference): array
```

**`PaymentLinkDriverInterface`** methods:
```php
createPaymentLink(array $data): array
updatePaymentLink(string $id, array $data): array
getPaymentLink(string $id): array
listPaymentLinks(array $params = []): array
disablePaymentLink(string $id): array
```

### 1.2 Add New Events

```
src/Events/
├── SubscriptionCreated.php    (NEW)
├── SubscriptionCancelled.php  (NEW)
├── SubscriptionRenewed.php    (NEW)
├── TransferSuccessful.php     (NEW)
├── TransferFailed.php         (NEW)
├── DisputeCreated.php         (NEW)
└── ChargebackCreated.php      (NEW)
```

### 1.3 Add New Exceptions

```
src/Exceptions/
├── PaymentException.php        (existing)
├── WebhookException.php        (existing)
├── SubscriptionException.php   (NEW)
└── DisbursementException.php   (NEW)
```

---

## Phase 2 — Enhance Existing Drivers

### 2.1 Paystack — Add Subscription + Disbursement

Paystack has first-class subscription and transfer APIs.

**Implement `SubscriptionDriverInterface`:**
- `POST /plan` — create plan
- `PUT /plan/:code` — update plan
- `GET /plan/:code` — get plan
- `GET /plan` — list plans
- `POST /subscription` — create subscription (customer + plan)
- `GET /subscription/:code` — get subscription
- `POST /subscription/disable` — cancel
- `POST /subscription/enable` — resume

**Implement `DisbursementDriverInterface`:**
- `POST /transferrecipient` — create recipient
- `POST /transfer` — initiate transfer
- `POST /transfer/bulk` — bulk transfer
- `GET /transfer/verify/:reference` — verify
- `GET /bank` — list banks
- `GET /bank/resolve?account_number&bank_code` — resolve account

**Webhook additions:**
- `invoice.create`, `invoice.update`, `invoice.payment_failed`
- `subscription.create`, `subscription.disable`, `subscription.expiry_date_update`
- `transfer.success`, `transfer.failed`, `transfer.reversed`

### 2.2 Flutterwave — Add Subscription + Disbursement + Mobile Money

**Implement `SubscriptionDriverInterface`:**
- `POST /payment-plans` — create plan
- `PUT /payment-plans/:id` — update plan
- `GET /payment-plans/:id` — get plan
- `GET /payment-plans` — list plans
- `PUT /payment-plans/:id/cancel` — cancel plan
- `POST /payments` with `payment_plan` field — subscribe customer

**Implement `DisbursementDriverInterface`:**
- `POST /transfers` — single transfer
- `POST /bulk-transfers` — bulk transfer
- `GET /transfers/:id` — verify
- `GET /banks/:country` — list banks
- `GET /accounts/resolve` — resolve account

**Additional Flutterwave-specific:**
- Mobile Money (Ghana MoMo, Uganda MoMo, Rwanda MoMo, Zambia MoMo)
- USSD (`payment_options: "ussd"`)
- M-Pesa (Kenya)

### 2.3 Stripe — Add Subscription

**Implement `SubscriptionDriverInterface`:**
- `POST /products` + `POST /prices` — create plan
- `POST /customers` — create customer
- `POST /subscriptions` — create subscription
- `DELETE /subscriptions/:id` — cancel
- `POST /subscriptions/:id` with `pause_collection` — pause

**Additional Stripe-specific:**
- Payment Method management (attach/detach cards)
- Setup Intents (save card for future use)
- Customer Portal (self-service subscription management)

### 2.4 Seerbit — Add Subscription + Virtual Account

**Implement `SubscriptionDriverInterface`:**
- Seerbit recurring payment via tokenization
- `POST /recurring/subscribes` — create subscription
- `PUT /recurring/subscribes/:code/cancel` — cancel

**Implement `VirtualAccountDriverInterface`:**
- `POST /virtual-accounts` — create virtual account
- `GET /virtual-accounts/:reference` — get virtual account

---

## Phase 3 — New Nigerian Providers

### 3.1 Monnify (by Moniepoint)

**Priority:** HIGH — dominant bank transfer gateway in Nigeria

**Supports:**
- One-time payment (card, bank transfer, USSD, NQR)
- Virtual account (reserved accounts)
- Disbursements
- Split payments

**Files to create:**
```
src/Drivers/MonnifyDriver.php
src/Webhooks/MonnifyWebhookHandler.php
```

**Auth:** Basic Auth (API Key + Secret Key → Base64)

**Key endpoints (`https://api.monnify.com`):**
| Feature                | Method | Endpoint                                                |
| ---------------------- | ------ | ------------------------------------------------------- |
| Get access token       | POST   | `/api/v1/auth/login`                                    |
| Initialize transaction | POST   | `/api/v1/merchant/transactions/init-transaction`        |
| Verify transaction     | GET    | `/api/v2/merchant/transactions/query?paymentReference=` |
| Reserve account        | POST   | `/api/v2/bank-transfer/reserved-accounts`               |
| Get reserved account   | GET    | `/api/v2/bank-transfer/reserved-accounts/{accountRef}`  |
| Initiate transfer      | POST   | `/api/v2/disbursements/single`                          |
| Bulk transfer          | POST   | `/api/v2/disbursements/batch`                           |
| List banks             | GET    | `/api/v1/sdk/transactions/banks`                        |
| Resolve account        | GET    | `/api/v1/disbursements/account/validate`                |

**Config entry:**
```php
'monnify' => [
    'driver' => 'monnify',
    'api_key' => env('MONNIFY_API_KEY'),
    'secret_key' => env('MONNIFY_SECRET_KEY'),
    'contract_code' => env('MONNIFY_CONTRACT_CODE'),
    'base_url' => env('MONNIFY_BASE_URL', 'https://api.monnify.com'),
    'callback_url' => env('MONNIFY_CALLBACK_URL'),
    'webhook_secret' => env('MONNIFY_WEBHOOK_SECRET'),
],
```

**Webhook events:**
- `SUCCESSFUL_TRANSACTION`
- `FAILED_TRANSACTION`
- `REVERSED_TRANSACTION`
- `SUCCESSFUL_DISBURSEMENT`
- `FAILED_DISBURSEMENT`
- `RESERVED_ACCOUNT_FUND_CREDIT`

### 3.2 Interswitch (Quickteller / WebPay)

**Priority:** MEDIUM — widely used for institutional and government payments

**Supports:**
- Card payments (Verve, Mastercard, Visa)
- USSD
- Bank account

**Files to create:**
```
src/Drivers/InterswitchDriver.php
src/Webhooks/InterswitchWebhookHandler.php
```

**Auth:** HMAC-SHA256 signature with client ID + timestamp

**Key endpoints (`https://qa.interswitchgroup.com` / `https://passport.interswitchgroup.com`):**
| Feature            | Method | Endpoint                                               |
| ------------------ | ------ | ------------------------------------------------------ |
| Get access token   | POST   | `/passport/oauth/token`                                |
| Initialize payment | POST   | `/api/v2/purchases`                                    |
| Query transaction  | GET    | `/api/v2/purchases/{productId}/{transactionReference}` |
| Requery            | POST   | `/api/v2/purchases/requery`                            |

**Config entry:**
```php
'interswitch' => [
    'driver' => 'interswitch',
    'client_id' => env('INTERSWITCH_CLIENT_ID'),
    'client_secret' => env('INTERSWITCH_CLIENT_SECRET'),
    'product_id' => env('INTERSWITCH_PRODUCT_ID'),
    'pay_item_id' => env('INTERSWITCH_PAY_ITEM_ID'),
    'base_url' => env('INTERSWITCH_BASE_URL', 'https://passport.interswitchgroup.com'),
    'callback_url' => env('INTERSWITCH_CALLBACK_URL'),
    'environment' => env('INTERSWITCH_ENV', 'sandbox'), // sandbox | production
],
```

### 3.3 Remita

**Priority:** MEDIUM — dominant for government/institutional payments (IPPIS, GIFMIS, etc.)

**Supports:**
- One-time payment
- Recurring Direct Debit (RRR — Remita Retrieval Reference)
- Mandate management (standing orders / direct debit)

**Files to create:**
```
src/Drivers/RemitaDriver.php
src/Webhooks/RemitaWebhookHandler.php
```

**Auth:** SHA-512 hash (merchantId + serviceTypeId + orderId + totalAmount + apiKey)

**Key endpoints (`https://remitademo.net/remita/exapp/api`):**
| Feature           | Method | Endpoint                                      |
| ----------------- | ------ | --------------------------------------------- |
| Generate RRR      | POST   | `/v1/send/payment/form/data`                  |
| Query transaction | GET    | `/v1/send/payment/query/{rrr}`                |
| Mandate setup     | POST   | `/v1/send/payment/mandate/setup`              |
| Mandate status    | GET    | `/v1/send/payment/mandate/status/{mandateId}` |
| Direct debit      | POST   | `/v1/send/payment/debit/account`              |

**Config entry:**
```php
'remita' => [
    'driver' => 'remita',
    'merchant_id' => env('REMITA_MERCHANT_ID'),
    'api_key' => env('REMITA_API_KEY'),
    'service_type_id' => env('REMITA_SERVICE_TYPE_ID'),
    'base_url' => env('REMITA_BASE_URL', 'https://remitademo.net/remita/exapp/api'),
    'callback_url' => env('REMITA_CALLBACK_URL'),
    'environment' => env('REMITA_ENV', 'demo'), // demo | production
],
```

**Subscription-specific (Direct Debit / Mandate):**
- `createMandate()` — sets up standing order
- `getMandateStatus()` — checks mandate
- `debitAccount()` — charges mandate
- `cancelMandate()` — cancels standing order

### 3.4 Squad (by GTCo)

**Priority:** MEDIUM — growing fintech gateway

**Supports:**
- Card payments
- Bank transfers
- USSD
- Virtual accounts
- Disbursements

**Files to create:**
```
src/Drivers/SquadDriver.php
src/Webhooks/SquadWebhookHandler.php
```

**Auth:** Bearer token (secret key)

**Key endpoints (`https://sandbox-api-d.squadco.com`):**
| Feature                | Method | Endpoint                                |
| ---------------------- | ------ | --------------------------------------- |
| Initialize payment     | POST   | `/transaction/initiate`                 |
| Verify transaction     | GET    | `/transaction/verify/{transaction_ref}` |
| Create virtual account | POST   | `/virtual-account`                      |
| Initiate transfer      | POST   | `/payout/initiate`                      |
| Verify transfer        | POST   | `/payout/requery`                       |

**Config entry:**
```php
'squad' => [
    'driver' => 'squad',
    'api_key' => env('SQUAD_API_KEY'),
    'secret_key' => env('SQUAD_SECRET_KEY'),
    'base_url' => env('SQUAD_BASE_URL', 'https://sandbox-api-d.squadco.com'),
    'callback_url' => env('SQUAD_CALLBACK_URL'),
    'webhook_secret' => env('SQUAD_WEBHOOK_SECRET'),
],
```

### 3.5 BudPay

**Priority:** LOW-MEDIUM — relatively new, growing

**Supports:**
- Card payments
- Bank transfer
- Crypto (optional)

**Files to create:**
```
src/Drivers/BudpayDriver.php
src/Webhooks/BudpayWebhookHandler.php
```

---

## Phase 4 — Global Providers

### 4.1 PayPal

**Priority:** HIGH — dominant globally (US, EU, Asia)

**Supports:**
- One-time payment (PayPal Checkout, Advanced Card Payments)
- Recurring / Subscriptions (PayPal Billing Plans + Agreements)
- Payouts (mass pay)
- Refunds

**Files to create:**
```
src/Drivers/PaypalDriver.php
src/Webhooks/PaypalWebhookHandler.php
```

**Auth:** OAuth 2.0 (client_id + client_secret → Bearer token)

**Key endpoints (`https://api-m.sandbox.paypal.com`):**
| Feature               | Method | Endpoint                                   |
| --------------------- | ------ | ------------------------------------------ |
| Get access token      | POST   | `/v1/oauth2/token`                         |
| Create order          | POST   | `/v2/checkout/orders`                      |
| Capture order         | POST   | `/v2/checkout/orders/{id}/capture`         |
| Get order             | GET    | `/v2/checkout/orders/{id}`                 |
| Refund capture        | POST   | `/v2/payments/captures/{captureId}/refund` |
| Create product        | POST   | `/v1/catalogs/products`                    |
| Create plan           | POST   | `/v1/billing/plans`                        |
| Create subscription   | POST   | `/v1/billing/subscriptions`                |
| Cancel subscription   | POST   | `/v1/billing/subscriptions/{id}/cancel`    |
| Suspend subscription  | POST   | `/v1/billing/subscriptions/{id}/suspend`   |
| Activate subscription | POST   | `/v1/billing/subscriptions/{id}/activate`  |
| Create payout         | POST   | `/v1/payments/payouts`                     |

**Config entry:**
```php
'paypal' => [
    'driver' => 'paypal',
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
    'callback_url' => env('PAYPAL_CALLBACK_URL'),
    'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    'environment' => env('PAYPAL_ENV', 'sandbox'), // sandbox | live
],
```

**Webhook events:**
- `PAYMENT.CAPTURE.COMPLETED`
- `PAYMENT.CAPTURE.DENIED`
- `BILLING.SUBSCRIPTION.CREATED`
- `BILLING.SUBSCRIPTION.CANCELLED`
- `BILLING.SUBSCRIPTION.SUSPENDED`
- `BILLING.SUBSCRIPTION.PAYMENT.FAILED`

### 4.2 Razorpay (India)

**Priority:** HIGH for India market

**Supports:**
- Cards, UPI, Net Banking, Wallets
- Subscriptions (Recurring)
- Routes (split payments)
- Payouts

**Files to create:**
```
src/Drivers/RazorpayDriver.php
src/Webhooks/RazorpayWebhookHandler.php
```

**Auth:** Basic Auth (key_id : key_secret)

**Config entry:**
```php
'razorpay' => [
    'driver' => 'razorpay',
    'key_id' => env('RAZORPAY_KEY_ID'),
    'key_secret' => env('RAZORPAY_KEY_SECRET'),
    'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
    'callback_url' => env('RAZORPAY_CALLBACK_URL'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
],
```

### 4.3 M-Pesa / Safaricom Daraja (Kenya + East Africa)

**Priority:** HIGH for East Africa — M-Pesa is the dominant payment method in Kenya

**Supports:**
- STK Push (Lipa na M-Pesa Online) — one-time
- C2B (Customer to Business)
- B2C (Business to Customer) — disbursement
- B2B
- Transaction Status Query

**Files to create:**
```
src/Drivers/MpesaDriver.php
src/Webhooks/MpesaWebhookHandler.php
```

**Auth:** OAuth 2.0 (consumer_key + consumer_secret → Bearer token)

**Key endpoints (`https://sandbox.safaricom.co.ke`):**
| Feature            | Method | Endpoint                                           |
| ------------------ | ------ | -------------------------------------------------- |
| Get access token   | GET    | `/oauth/v1/generate?grant_type=client_credentials` |
| STK Push           | POST   | `/mpesa/stkpush/v1/processrequest`                 |
| STK Push query     | POST   | `/mpesa/stkpushquery/v1/query`                     |
| C2B register URL   | POST   | `/mpesa/c2b/v1/registerurl`                        |
| B2C payment        | POST   | `/mpesa/b2c/v1/paymentrequest`                     |
| Transaction status | POST   | `/mpesa/transactionstatus/v3/query`                |
| Account balance    | POST   | `/mpesa/accountbalance/v1/query`                   |
| Reversal           | POST   | `/mpesa/reversal/v1/request`                       |

**Config entry:**
```php
'mpesa' => [
    'driver' => 'mpesa',
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'passkey' => env('MPESA_PASSKEY'),
    'shortcode' => env('MPESA_SHORTCODE'),
    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
    'base_url' => env('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke'),
    'callback_url' => env('MPESA_CALLBACK_URL'),
    'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),
    'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
    'environment' => env('MPESA_ENV', 'sandbox'), // sandbox | production
],
```

### 4.4 MTN Mobile Money (MoMo API)

**Priority:** HIGH for West and Central Africa — MTN covers Ghana, Uganda, Rwanda, Cameroon, Ivory Coast, etc.

**Supports:**
- Collections (one-time, recurring)
- Disbursements
- Remittances

**Files to create:**
```
src/Drivers/MtnMomoDriver.php
src/Webhooks/MtnMomoWebhookHandler.php
```

**Auth:** API Key + OAuth 2.0 (subscription key in header)

**Key endpoints (`https://sandbox.momodeveloper.mtn.com`):**
| Feature             | Method | Endpoint                                      |
| ------------------- | ------ | --------------------------------------------- |
| Create API user     | POST   | `/v1_0/apiuser`                               |
| Create API key      | POST   | `/v1_0/apiuser/{userId}/apikey`               |
| Get access token    | POST   | `/collection/token/`                          |
| Request to pay      | POST   | `/collection/v1_0/requesttopay`               |
| Get payment status  | GET    | `/collection/v1_0/requesttopay/{referenceId}` |
| Transfer (disburse) | POST   | `/disbursement/v1_0/transfer`                 |
| Transfer status     | GET    | `/disbursement/v1_0/transfer/{referenceId}`   |

**Config entry:**
```php
'mtn_momo' => [
    'driver' => 'mtn_momo',
    'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
    'api_user' => env('MTN_MOMO_API_USER'),
    'api_key' => env('MTN_MOMO_API_KEY'),
    'collection_subscription_key' => env('MTN_MOMO_COLLECTION_KEY'),
    'disbursement_subscription_key' => env('MTN_MOMO_DISBURSEMENT_KEY'),
    'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
    'callback_url' => env('MTN_MOMO_CALLBACK_URL'),
    'environment' => env('MTN_MOMO_ENV', 'sandbox'), // sandbox | production
    'currency' => env('MTN_MOMO_CURRENCY', 'EUR'), // EUR in sandbox
],
```

### 4.5 Paddle (SaaS / Subscription-first)

**Priority:** MEDIUM — excellent for SaaS, handles VAT/tax

**Supports:**
- One-time (checkout links)
- Subscriptions (first-class)
- Tax/VAT compliance (merchant of record)

**Files to create:**
```
src/Drivers/PaddleDriver.php
src/Webhooks/PaddleWebhookHandler.php
```

**Config entry:**
```php
'paddle' => [
    'driver' => 'paddle',
    'vendor_id' => env('PADDLE_VENDOR_ID'),
    'vendor_auth_code' => env('PADDLE_VENDOR_AUTH_CODE'),
    'public_key' => env('PADDLE_PUBLIC_KEY'),
    'base_url' => env('PADDLE_BASE_URL', 'https://sandbox-vendors.paddle.com/api/2.0'),
    'callback_url' => env('PADDLE_CALLBACK_URL'),
    'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
],
```

---

## Phase 5 — Subscription Architecture

### 5.1 Subscription Flow

```
1. Create Plan (once)
      ↓
2. Initialize Payment (with plan code)
      ↓
3. Customer completes payment
      ↓
4. Gateway creates Subscription
      ↓
5. Webhook → SubscriptionCreated event
      ↓
6. Periodic: Gateway charges on schedule
      ↓
7. Webhook → SubscriptionRenewed / PaymentFailed event
      ↓
8. Cancel / Pause / Resume (as needed)
```

### 5.2 Plan Interval Standardization

All drivers should normalize plan intervals to a common format:

```php
// Standard input
$plan = [
    'name' => 'Monthly Pro Plan',
    'amount' => 5000.00,         // in major currency unit (e.g. NGN 5,000)
    'currency' => 'NGN',
    'interval' => 'monthly',     // daily | weekly | monthly | quarterly | biannually | annually
    'description' => '...',
    'trial_days' => 14,          // optional
    'invoice_limit' => 12,       // optional — 0 = unlimited
];
```

Each driver maps this to its own API format internally.

### 5.3 Subscription Response Normalization

All drivers should return a normalized subscription response:

```php
// Standard output
[
    'status' => true,
    'data' => [
        'subscription_code' => 'SUB_xxx',
        'plan_code' => 'PLN_xxx',
        'customer_email' => 'user@example.com',
        'customer_code' => 'CUS_xxx',
        'status' => 'active',        // active | cancelled | paused | completed | non-renewing
        'amount' => 5000.00,
        'currency' => 'NGN',
        'interval' => 'monthly',
        'next_payment_date' => '2026-06-07',
        'start_date' => '2026-05-07',
        'gateway' => 'paystack',
        'raw' => [...],              // original gateway response
    ],
]
```

---

## Phase 6 — Disbursement Architecture

### 6.1 Transfer Flow

```
1. Resolve account (optional — confirm bank details)
      ↓
2. Create recipient / beneficiary
      ↓
3. Initiate transfer
      ↓
4. OTP confirmation (if required by gateway)
      ↓
5. Webhook → TransferSuccessful / TransferFailed event
```

### 6.2 Transfer Response Normalization

```php
[
    'status' => true,
    'data' => [
        'transfer_code' => 'TRF_xxx',
        'reference' => 'ref_xxx',
        'recipient_name' => 'John Doe',
        'recipient_account' => '0123456789',
        'bank_code' => '058',
        'bank_name' => 'GTBank',
        'amount' => 10000.00,
        'currency' => 'NGN',
        'status' => 'pending',       // pending | success | failed | reversed
        'reason' => 'Salary payment',
        'gateway' => 'paystack',
        'raw' => [...],
    ],
]
```

---

## Phase 7 — Database Migrations (Optional but Recommended)

For packages that want local transaction storage:

```
database/migrations/
├── create_payment_transactions_table.php
├── create_payment_plans_table.php
├── create_payment_subscriptions_table.php
└── create_payment_transfers_table.php
```

**`payment_transactions` table:**
```sql
id, gateway, reference, amount, currency, status,
customer_email, payment_method, metadata (json),
gateway_response (json), created_at, updated_at
```

**`payment_plans` table:**
```sql
id, gateway, plan_code, name, amount, currency,
interval, trial_days, invoice_limit, status,
metadata (json), created_at, updated_at
```

**`payment_subscriptions` table:**
```sql
id, gateway, subscription_code, plan_id (fk),
customer_email, customer_code, amount, currency,
status, next_payment_date, start_date, end_date,
metadata (json), created_at, updated_at
```

**`payment_transfers` table:**
```sql
id, gateway, transfer_code, reference,
recipient_account, bank_code, bank_name, amount,
currency, status, reason, metadata (json),
created_at, updated_at
```

---

## Phase 8 — Test Coverage

```
tests/
├── Unit/
│   ├── Drivers/
│   │   ├── PaystackDriverTest.php
│   │   ├── FlutterwaveDriverTest.php
│   │   ├── StripeDriverTest.php
│   │   ├── SeerbitDriverTest.php
│   │   ├── MonnifyDriverTest.php
│   │   ├── PaypalDriverTest.php
│   │   └── MpesaDriverTest.php
│   ├── Webhooks/
│   │   ├── PaystackWebhookHandlerTest.php
│   │   ├── FlutterwaveWebhookHandlerTest.php
│   │   └── ...
│   └── Managers/
│       ├── PaymentManagerTest.php
│       └── WebhookManagerTest.php
├── Feature/
│   ├── PaymentFlowTest.php
│   ├── SubscriptionFlowTest.php
│   └── DisbursementFlowTest.php
└── TestCase.php
```

---

## Implementation Priority Summary

### Immediate (High ROI for Nigeria + Africa)

| Driver                | One-Time | Subscription | Disbursement | Priority |
| --------------------- | -------- | ------------ | ------------ | -------- |
| Paystack (enhance)    | ✅ Done   | 🔲 Add        | 🔲 Add        | P0       |
| Flutterwave (enhance) | ✅ Done   | 🔲 Add        | 🔲 Add        | P0       |
| Monnify (new)         | 🔲        | —            | 🔲 Add        | P0       |
| M-Pesa (new)          | 🔲        | —            | 🔲 B2C        | P0       |
| MTN MoMo (new)        | 🔲        | 🔲            | 🔲            | P1       |

### Short-term (Global Coverage)

| Driver           | One-Time | Subscription | Disbursement | Priority |
| ---------------- | -------- | ------------ | ------------ | -------- |
| PayPal (new)     | 🔲        | 🔲            | 🔲 Payouts    | P1       |
| Stripe (enhance) | ✅ Done   | 🔲 Add        | —            | P1       |
| Razorpay (new)   | 🔲        | 🔲            | 🔲            | P2       |
| Squad (new)      | 🔲        | —            | 🔲            | P2       |

### Medium-term (Specialised)

| Driver            | Purpose                    | Priority |
| ----------------- | -------------------------- | -------- |
| Remita (new)      | Nigerian gov/institutional | P2       |
| Interswitch (new) | Legacy institutional       | P3       |
| Paddle (new)      | SaaS / global tax          | P3       |
| BudPay (new)      | Nigerian crypto/card       | P3       |

---

## File Creation Checklist

For each new provider, create:
- [ ] `src/Drivers/{Provider}Driver.php`
- [ ] `src/Webhooks/{Provider}WebhookHandler.php`
- [ ] Config entry in `config/payment-gateways.php`
- [ ] Driver registration in `PaymentManager::create{Provider}Driver()`
- [ ] Handler registration in `WebhookManager::createHandler()` match statement
- [ ] Driver registration in `PaymentServiceProvider::registerDrivers()`
- [ ] Webhook event_mapping in config
- [ ] Tests in `tests/Unit/Drivers/{Provider}DriverTest.php`
- [ ] Usage examples in `examples/`

For subscription support in existing providers, also add:
- [ ] `src/Contracts/SubscriptionDriverInterface.php`
- [ ] `src/Events/SubscriptionCreated.php`
- [ ] `src/Events/SubscriptionCancelled.php`
- [ ] `src/Events/SubscriptionRenewed.php`
- [ ] Webhook event mappings for subscription events

---

## Key Design Principles

1. **Normalization first** — every driver must return the same response shape so callers don't need gateway-specific code.
2. **Composable interfaces** — drivers implement only the interfaces they support; capabilities discovered at runtime.
3. **No breaking changes** — existing `PaymentDriverInterface` is not changed; new interfaces are additive.
4. **Capability detection** — `Payment::driver('paystack') instanceof SubscriptionDriverInterface` tells callers what a driver supports.
5. **Currency in major units** — all amounts go in and come out in major currency units (NGN, USD, etc.), conversion to kobo/cents is internal.
6. **Idempotent references** — drivers always accept a caller-supplied reference and generate one only as fallback.
