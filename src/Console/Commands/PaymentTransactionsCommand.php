<?php

namespace NexusPay\PaymentMadeEasy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use NexusPay\PaymentMadeEasy\Models\PaymentTransaction;

class PaymentTransactionsCommand extends Command
{
    protected $signature = 'payment:transactions
                            {--gateway= : Filter by gateway slug (e.g. paystack)}
                            {--status= : Filter by transaction status}
                            {--limit=25 : Maximum rows (1–500)}';

    protected $description = 'List recorded payment transactions from the database';

    public function handle(): int
    {
        if (!Schema::hasTable('payment_transactions')) {
            $this->error('Table payment_transactions not found. Publish and run migrations:');
            $this->line('  php artisan vendor:publish --tag=payment-gateways-migrations');
            $this->line('  php artisan migrate');

            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));

        $query = PaymentTransaction::query()->orderByDesc('id');

        if ($gateway = $this->option('gateway')) {
            $query->where('gateway', $gateway);
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->warn('No transactions matched your filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Gateway', 'Reference', 'Amount', 'Currency', 'Status', 'Updated'],
            $rows->map(fn (PaymentTransaction $t) => [
                $t->id,
                $t->gateway,
                $t->reference,
                (string) $t->amount,
                $t->currency,
                $t->status,
                $t->updated_at?->toDateTimeString() ?? '',
            ])->all()
        );

        $this->line('Showing ' . $rows->count() . ' row(s).');

        return self::SUCCESS;
    }
}
