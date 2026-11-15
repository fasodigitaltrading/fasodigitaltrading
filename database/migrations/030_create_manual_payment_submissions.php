<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_payment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('method', 32);
            $table->string('recipient_snapshot', 191);
            $table->decimal('expected_amount', 14, 2);
            $table->string('currency', 10);
            $table->string('transaction_reference', 191);
            $table->string('proof_path', 255);
            $table->char('proof_sha256', 64);
            $table->string('status', 20)->default('PENDING');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['method', 'transaction_reference']);
            $table->index(['status', 'created_at']);
            $table->index(['subscription_order_id', 'status']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('manual_payment_submissions')
            && DB::table('manual_payment_submissions')->exists()) {
            throw new LogicException('Manual payment evidence exists. Preserve it and use a forward migration.');
        }

        Schema::dropIfExists('manual_payment_submissions');
    }
};
