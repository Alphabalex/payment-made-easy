<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->index();
            $table->string('reference')->unique();
            $table->string('gateway_reference')->nullable()->index();
            // Recipient info
            $table->string('recipient_code')->nullable()->index();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_account', 30)->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_name')->nullable();
            // Transfer details
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('NGN');
            $table->string('narration')->nullable();
            $table->string('status', 20)->default('pending')->index();  // pending | successful | failed | reversed
            // Polymorphic initiator
            $table->nullableMorphs('initiator');
            // Extras
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transfers');
    }
};
