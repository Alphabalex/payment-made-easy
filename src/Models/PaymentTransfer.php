<?php

namespace NexusPay\PaymentMadeEasy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentTransfer extends Model
{
    protected $fillable = [
        'gateway',
        'reference',
        'gateway_reference',
        'recipient_code',
        'recipient_name',
        'recipient_account',
        'bank_code',
        'bank_name',
        'amount',
        'currency',
        'narration',
        'status',
        'initiator_type',
        'initiator_id',
        'metadata',
        'raw_response',
        'completed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'metadata'     => 'array',
        'raw_response' => 'array',
        'completed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'successful');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markSuccessful(string $gatewayReference = null): bool
    {
        return $this->update([
            'status'            => 'successful',
            'gateway_reference' => $gatewayReference ?? $this->gateway_reference,
            'completed_at'      => now(),
        ]);
    }

    public function markFailed(): bool
    {
        return $this->update(['status' => 'failed']);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function initiator(): MorphTo
    {
        return $this->morphTo();
    }
}
