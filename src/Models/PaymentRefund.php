<?php

namespace NexusPay\PaymentMadeEasy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_transaction_id',
        'gateway',
        'reference',
        'gateway_refund_id',
        'amount',
        'currency',
        'raw_payload',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'raw_payload' => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
