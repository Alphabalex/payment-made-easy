<?php

namespace NexusPay\PaymentMadeEasy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentSubscription extends Model
{
    protected $fillable = [
        'gateway',
        'plan_code',
        'plan_name',
        'subscription_code',
        'email',
        'amount',
        'currency',
        'interval',
        'status',
        'subscriber_type',
        'subscriber_id',
        'invoice_limit',
        'invoices_paid',
        'trial_ends_at',
        'next_payment_at',
        'cancelled_at',
        'metadata',
        'raw_response',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'invoice_limit'   => 'integer',
        'invoices_paid'   => 'integer',
        'trial_ends_at'   => 'datetime',
        'next_payment_at' => 'datetime',
        'cancelled_at'    => 'datetime',
        'metadata'        => 'array',
        'raw_response'    => 'array',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function cancel(): bool
    {
        return $this->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    public function pause(): bool
    {
        return $this->update(['status' => 'paused']);
    }

    public function resume(): bool
    {
        return $this->update(['status' => 'active']);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }
}
