<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_payment_otps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('method', 32);
            $table->string('recipient_snapshot', 191);
            $table->decimal('expected_amount', 14, 2);
            $table->string('currency', 10);
            $table->string('code_hash', 255);
            $table->text('code_ciphertext')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['subscription_order_id', 'used_at', 'invalidated_at'], 'manual_payment_otps_order_state_idx');
            $table->index(['user_id', 'created_at'], 'manual_payment_otps_user_created_idx');
        });

        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->foreignId('manual_payment_otp_id')->nullable()->unique()->constrained('manual_payment_otps')->restrictOnDelete();
            $table->decimal('declared_amount', 14, 2)->nullable();
            $table->timestamp('transaction_occurred_at')->nullable();
            $table->string('admin_email_status', 24)->default('BLOCKED');
            $table->timestamp('admin_email_sent_at')->nullable();
            $table->timestamp('admin_email_last_attempt_at')->nullable();
            $table->index(['admin_email_status', 'status'], 'manual_payment_admin_email_status_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('manual_payment_otps') && DB::table('manual_payment_otps')->exists()) {
            throw new LogicException('Payment OTP records exist. Preserve them and use a forward migration.');
        }

        if (Schema::hasTable('manual_payment_submissions') && DB::table('manual_payment_submissions')->exists()) {
            throw new LogicException('Manual payment records exist. Preserve them and use a forward migration.');
        }

        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->dropIndex('manual_payment_admin_email_status_idx');
            $table->dropConstrainedForeignId('manual_payment_otp_id');
            $table->dropColumn([
                'declared_amount',
                'transaction_occurred_at',
                'admin_email_status',
                'admin_email_sent_at',
                'admin_email_last_attempt_at',
            ]);
        });

        Schema::dropIfExists('manual_payment_otps');
    }
};
