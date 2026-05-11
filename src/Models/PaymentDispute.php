<?php

namespace NexusPay\PaymentMadeEasy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDispute extends Model
{
    public const KIND_DISPUTE = 'dispute';

    public const KIND_CHARGEBACK = 'chargeback';

    protected $fillable = [
        'payment_transaction_id',
        'gateway',
        'kind',
        'reference',
        'gateway_dispute_id',
        'amount',
        'currency',
        'status',
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

    public function isChargeback(): bool
    {
        return $this->kind === self::KIND_CHARGEBACK;
    }
}
