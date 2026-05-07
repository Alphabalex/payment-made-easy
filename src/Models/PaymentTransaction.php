<?php

namespace NexusPay\PaymentMadeEasy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'gateway',
        'reference',
        'gateway_reference',
        'amount',
        'currency',
        'status',
        'email',
        'customer_name',
        'phone',
        'payable_type',
        'payable_id',
        'description',
        'callback_url',
        'metadata',
        'raw_response',
        'paid_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'metadata'     => 'array',
        'raw_response' => 'array',
        'paid_at'      => 'datetime',
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
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
            'status'             => 'successful',
            'gateway_reference'  => $gatewayReference ?? $this->gateway_reference,
            'paid_at'            => now(),
        ]);
    }

    public function markFailed(): bool
    {
        return $this->update(['status' => 'failed']);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
