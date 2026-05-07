<?php

namespace NexusPay\PaymentMadeEasy\Console\Commands;

use Illuminate\Console\Command;
use NexusPay\PaymentMadeEasy\Models\PaymentWebhookLog;
use NexusPay\PaymentMadeEasy\WebhookManager;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class PaymentWebhookReplayCommand extends Command
{
    protected $signature = 'payment:webhook-replay
                            {id : The PaymentWebhookLog ID to replay}
                            {--dry-run : Parse and print the event without dispatching}';

    protected $description = 'Replay a previously logged webhook event from the database';

    public function handle(WebhookManager $manager): int
    {
        if (!config('payment-gateways.recording.enabled', false)) {
            $this->error('Database recording is disabled. Set PAYMENT_RECORDING_ENABLED=true and run migrations first.');
            return self::FAILURE;
        }

        $id  = (int) $this->argument('id');
        $log = PaymentWebhookLog::find($id);

        if (!$log) {
            $this->error("PaymentWebhookLog #{$id} not found.");
            return self::FAILURE;
        }

        $this->line("Replaying webhook log <fg=cyan>#{$id}</> — gateway: <fg=cyan>{$log->gateway}</>, event: <fg=cyan>{$log->event_type}</>");

        try {
            $handler = $manager->getHandler($log->gateway);

            // Build a synthetic Request from stored payload
            $request = new \Illuminate\Http\Request();
            $request->replace($log->payload);

            if ($this->option('dry-run')) {
                $this->info('[dry-run] Event would be dispatched:');
                $this->line(json_encode($log->payload, JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            $event = $handler->parsePayload($request);
            $handler->handle($event);

            $log->update([
                'status'       => 'processed',
                'processed_at' => now(),
            ]);

            $this->info("Webhook #{$id} replayed successfully.");
        } catch (WebhookException $e) {
            $this->error("Replay failed: " . $e->getMessage());

            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at'  => now(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
