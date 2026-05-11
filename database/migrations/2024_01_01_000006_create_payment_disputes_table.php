<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->constrained('payment_transactions')
                ->nullOnDelete();
            $table->string('gateway', 30)->index();
            $table->string('kind', 20)->index()->comment('dispute | chargeback');
            $table->string('reference')->index()->comment('Original payment reference');
            $table->string('gateway_dispute_id')->nullable()->index();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('status', 40)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_dispute_id'], 'payment_disputes_gateway_dispute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
    }
};
