<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->index();
            $table->string('plan_code')->index();
            $table->string('plan_name')->nullable();
            $table->string('subscription_code')->nullable()->unique();
            $table->string('email')->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('NGN');
            $table->string('interval', 20);    // monthly | weekly | annually | quarterly | daily
            $table->string('status', 20)->default('pending')->index();  // pending | active | paused | cancelled | expired
            // Polymorphic subscriber (e.g. User)
            $table->nullableMorphs('subscriber');
            // Timeline
            $table->integer('invoice_limit')->default(0)->comment('0 = unlimited');
            $table->integer('invoices_paid')->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Extras
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'status']);
            $table->index(['email', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_subscriptions');
    }
};
