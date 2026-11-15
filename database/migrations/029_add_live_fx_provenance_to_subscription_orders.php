<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table): void {
            $table->timestamp('fx_rate_updated_at')->nullable();
            $table->string('fx_provider', 32)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('subscription_orders')->whereNotNull('fx_rate_updated_at')->exists()
            || DB::table('subscription_orders')->whereNotNull('fx_provider')->exists()) {
            throw new LogicException('Live exchange-rate provenance exists. Preserve it and use a forward migration.');
        }

        Schema::table('subscription_orders', function (Blueprint $table): void {
            $table->dropColumn(['fx_rate_updated_at', 'fx_provider']);
        });
    }
};
