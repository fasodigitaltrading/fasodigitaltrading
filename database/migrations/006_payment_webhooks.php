<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('checkout_url', 2048)->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->unique(['provider', 'transaction_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('event_id', 191);
            $table->string('event_type', 100);
            $table->char('payload_hash', 64);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('processing_status', 20)->default('RECEIVED');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
            $table->index(['processing_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['provider', 'transaction_id']);
            $table->dropColumn(['checkout_url', 'idempotency_key']);
        });
    }
};
