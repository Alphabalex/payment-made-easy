<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')
                ->constrained('payment_transactions')
                ->cascadeOnDelete();
            $table->string('gateway', 30)->index();
            $table->string('reference')->index()->comment('Original payment reference (denormalized)');
            $table->string('gateway_refund_id')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_refund_id'], 'payment_refunds_gateway_refund_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
