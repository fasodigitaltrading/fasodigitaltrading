<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_orders', function (Blueprint $table): void {
            $table->id();
            // Retain financial order history; a customer with an order cannot be hard-deleted.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 36)->unique();
            $table->string('status', 32)->default('AWAITING_PAYMENT')->index();
            $table->string('plan_name_snapshot', 150);
            $table->text('plan_description_snapshot')->nullable();
            $table->json('plan_features_snapshot')->nullable();
            $table->decimal('amount_usd', 12, 2);
            $table->unsignedInteger('duration_days_snapshot');
            $table->unsignedInteger('max_mt5_accounts_snapshot');
            $table->decimal('capital_required_snapshot', 12, 2)->nullable();
            $table->unsignedBigInteger('amount_xof')->nullable();
            $table->decimal('fx_rate', 12, 4)->nullable();
            $table->date('fx_rate_as_of')->nullable();
            $table->text('fx_source_url')->nullable();
            $table->timestamp('fx_fetched_at')->nullable();
            $table->timestamp('quote_expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            // A nullable indexed pointer can be added without rebuilding existing
            // payment history on SQLite deployments.
            $table->unsignedBigInteger('subscription_order_id')->nullable();
            $table->index('subscription_order_id');
        });
    }

    public function down(): void
    {
        if ((Schema::hasTable('subscription_orders') && DB::table('subscription_orders')->exists())
            || (Schema::hasColumn('payments', 'subscription_order_id')
                && DB::table('payments')->whereNotNull('subscription_order_id')->exists())) {
            throw new LogicException('Subscription orders exist. Preserve their financial history and create a forward migration instead.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['subscription_order_id']);
            $table->dropColumn('subscription_order_id');
        });
        Schema::dropIfExists('subscription_orders');
    }
};
