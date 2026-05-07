<?php

namespace NexusPay\PaymentMadeEasy\Console\Commands;

use Illuminate\Console\Command;
use NexusPay\PaymentMadeEasy\PaymentManager;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;

class PaymentGatewaysCommand extends Command
{
    protected $signature = 'payment:gateways
                            {--configured : Show only gateways with credentials set}';

    protected $description = 'List all registered payment gateways and their capabilities';

    public function handle(PaymentManager $manager): int
    {
        $gateways  = config('payment-gateways.gateways', []);
        $default   = config('payment-gateways.default');
        $onlyConfigured = $this->option('configured');

        $rows = [];

        foreach ($gateways as $name => $config) {
            if ($onlyConfigured && !$this->isConfigured($config)) {
                continue;
            }

            try {
                $driver = $manager->driver($name);

                $rows[] = [
                    $name . ($name === $default ? ' *' : ''),
                    '✓',
                    $driver instanceof SubscriptionDriverInterface  ? '✓' : '—',
                    $driver instanceof DisbursementDriverInterface  ? '✓' : '—',
                    $driver instanceof VirtualAccountDriverInterface ? '✓' : '—',
                    $driver instanceof PaymentLinkDriverInterface   ? '✓' : '—',
                    $this->isConfigured($config) ? '<fg=green>ready</>' : '<fg=yellow>missing env</>',
                ];
            } catch (\Throwable $e) {
                $rows[] = [$name, '✗', '—', '—', '—', '—', '<fg=red>error: ' . $e->getMessage() . '</>'];
            }
        }

        if (empty($rows)) {
            $this->warn('No gateways found. Publish the config: php artisan vendor:publish --tag=payment-gateways-config');
            return self::SUCCESS;
        }

        $this->table(
            ['Gateway (* = default)', 'Payments', 'Subscriptions', 'Disbursements', 'Virtual Accounts', 'Payment Links', 'Status'],
            $rows
        );

        $this->newLine();
        $this->line("  <fg=green>*</> = default gateway  |  Total configured: " . count($gateways));

        return self::SUCCESS;
    }

    private function isConfigured(array $config): bool
    {
        // Check first credential key for each driver
        $credentialKeys = ['secret_key', 'api_key', 'client_secret', 'key_secret', 'consumer_secret'];

        foreach ($credentialKeys as $key) {
            if (!empty($config[$key])) {
                return true;
            }
        }

        return false;
    }
}
