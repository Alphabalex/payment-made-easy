<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->index();
            $table->string('reference')->unique();
            $table->string('gateway_reference')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('NGN');
            $table->string('status', 20)->default('pending')->index();
            // Customer info
            $table->string('email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('phone', 30)->nullable();
            // Polymorphic owner (e.g. link to User or Order model)
            $table->nullableMorphs('payable');
            // Extras
            $table->text('description')->nullable();
            $table->string('callback_url')->nullable();
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
