<?php

namespace NexusPay\PaymentMadeEasy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->status === 'partially_refunded';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
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

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'payment_transaction_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(PaymentDispute::class, 'payment_transaction_id');
    }

    public function totalRefundedAmount(): float
    {
        return (float) $this->refunds()->sum('amount');
    }
}
