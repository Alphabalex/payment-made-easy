<?php

namespace NexusPay\PaymentMadeEasy\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    protected $fillable = [
        'gateway',
        'event_type',
        'reference',
        'gateway_event_id',
        'status',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('status', 'received');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function markProcessed(): bool
    {
        return $this->update([
            'status'       => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): bool
    {
        return $this->update([
            'status'        => 'failed',
            'error_message' => $errorMessage,
            'processed_at'  => now(),
        ]);
    }

    public function markIgnored(): bool
    {
        return $this->update([
            'status'       => 'ignored',
            'processed_at' => now(),
        ]);
    }
}
