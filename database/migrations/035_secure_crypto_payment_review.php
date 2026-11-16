<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->string('verified_transaction_hash', 66)->nullable()->unique('manual_payment_verified_hash_unique');
            $table->string('verified_token_contract', 42)->nullable();
            $table->string('verified_recipient', 42)->nullable();
            $table->unsignedInteger('verified_confirmations')->nullable();
            $table->string('customer_email_status', 24)->default('NOT_SENT');
            $table->timestamp('customer_email_sent_at')->nullable();
            $table->timestamp('customer_email_last_attempt_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('manual_payment_submissions')
            && (DB::table('manual_payment_submissions')->whereNotNull('verified_transaction_hash')->exists()
                || DB::table('manual_payment_submissions')->where('customer_email_status', 'SENT')->exists())) {
            throw new LogicException('Verified crypto payments or customer confirmations exist. Preserve them and use a forward migration.');
        }

        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->dropUnique('manual_payment_verified_hash_unique');
            $table->dropColumn([
                'verified_transaction_hash',
                'verified_token_contract',
                'verified_recipient',
                'verified_confirmations',
                'customer_email_status',
                'customer_email_sent_at',
                'customer_email_last_attempt_at',
            ]);
        });
    }
};
