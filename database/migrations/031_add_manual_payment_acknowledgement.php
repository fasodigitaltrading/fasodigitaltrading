<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->timestamp('customer_acknowledged_at')->nullable();
            $table->string('customer_terms_version', 60)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('manual_payment_submissions')
            && DB::table('manual_payment_submissions')->whereNotNull('customer_acknowledged_at')->exists()) {
            throw new LogicException('Payment acknowledgements exist. Preserve them and use a forward migration.');
        }

        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->dropColumn(['customer_acknowledged_at', 'customer_terms_version']);
        });
    }
};
