<?php

namespace NexusPay\PaymentMadeEasy\Console\Commands;

use Illuminate\Console\Command;
use NexusPay\PaymentMadeEasy\PaymentManager;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;

class PaymentVerifyCommand extends Command
{
    protected $signature = 'payment:verify
                            {gateway : The gateway driver to use (e.g. paystack, stripe)}
                            {reference : The payment reference or ID to verify}
                            {--json : Output the full raw response as JSON}';

    protected $description = 'Verify a payment reference against a gateway';

    public function handle(PaymentManager $manager): int
    {
        $gateway   = $this->argument('gateway');
        $reference = $this->argument('reference');

        $this->info("Verifying <fg=cyan>{$reference}</> via <fg=cyan>{$gateway}</>...");

        try {
            $driver   = $manager->driver($gateway);
            $response = $driver->verifyPayment($reference);
        } catch (PaymentException $e) {
            $this->error("Payment verification failed: " . $e->getMessage());
            return self::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $this->error("Unknown gateway '{$gateway}'. Run <fg=yellow>php artisan payment:gateways</> to see available gateways.");
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($response, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        // Pretty summary
        $this->newLine();

        $status  = $this->extractStatus($response);
        $amount  = $this->extractAmount($response);
        $email   = $this->extractEmail($response);
        $paidAt  = $this->extractPaidAt($response);

        $statusColor = match ($status) {
            'success', 'successful', 'paid', 'captured', 'active', 'SUCCESSFUL' => 'green',
            'failed', 'FAILED'    => 'red',
            'pending', 'PENDING'  => 'yellow',
            default               => 'white',
        };

        $this->line("  Gateway    : <fg=cyan>{$gateway}</>");
        $this->line("  Reference  : <fg=cyan>{$reference}</>");
        $this->line("  Status     : <fg={$statusColor}>{$status}</>");

        if ($amount) {
            $this->line("  Amount     : {$amount}");
        }

        if ($email) {
            $this->line("  Email      : {$email}");
        }

        if ($paidAt) {
            $this->line("  Paid at    : {$paidAt}");
        }

        $this->newLine();
        $this->line("  Use <fg=yellow>--json</> for the full gateway response.");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Helpers — extract normalised values from varying response shapes
    // -------------------------------------------------------------------------

    private function extractStatus(array $response): string
    {
        // Paystack: data.status
        if (isset($response['data']['status'])) {
            return (string) $response['data']['status'];
        }
        // Razorpay: status at root
        if (isset($response['status']) && is_string($response['status'])) {
            return $response['status'];
        }
        // Flutterwave: data.status
        if (isset($response['data']['tx_ref'])) {
            return $response['data']['status'] ?? 'unknown';
        }
        // MTN MoMo: status at root
        if (isset($response['financialTransactionId'])) {
            return $response['status'] ?? 'unknown';
        }

        return 'unknown';
    }

    private function extractAmount(array $response): string
    {
        $raw = $response['data']['amount']
            ?? $response['amount']
            ?? null;

        if ($raw === null) {
            return '';
        }

        // Amounts in kobo / paise — if over 1000 and int, assume minor unit
        $currency = $response['data']['currency'] ?? $response['currency'] ?? '';

        if (is_int($raw) && $raw > 1000) {
            return number_format($raw / 100, 2) . ($currency ? " {$currency}" : '');
        }

        return number_format((float) $raw, 2) . ($currency ? " {$currency}" : '');
    }

    private function extractEmail(array $response): string
    {
        return $response['data']['customer']['email']
            ?? $response['data']['email']
            ?? $response['email']
            ?? '';
    }

    private function extractPaidAt(array $response): string
    {
        return $response['data']['paid_at']
            ?? $response['data']['created_at']
            ?? $response['created_at']
            ?? '';
    }
}
