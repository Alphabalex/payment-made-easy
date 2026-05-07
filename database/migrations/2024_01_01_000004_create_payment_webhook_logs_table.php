<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->index();
            $table->string('event_type', 80)->index();
            $table->string('reference')->nullable()->index();
            $table->string('gateway_event_id')->nullable()->index();
            $table->string('status', 20)->default('received');  // received | processed | failed | ignored
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
