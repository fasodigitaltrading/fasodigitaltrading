<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('plan_name_snapshot')->nullable();
            $table->decimal('plan_price_snapshot', 12, 2)->nullable();
            $table->string('plan_currency_snapshot', 10)->nullable();
            $table->unsignedInteger('plan_duration_days_snapshot')->nullable();
            $table->unsignedInteger('plan_max_mt5_accounts_snapshot')->nullable();
            $table->decimal('plan_capital_required_snapshot', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        $snapshotsExist = DB::table('subscriptions')->where(function ($query) {
            $query->whereNotNull('plan_name_snapshot')
                ->orWhereNotNull('plan_price_snapshot')
                ->orWhereNotNull('plan_currency_snapshot')
                ->orWhereNotNull('plan_duration_days_snapshot')
                ->orWhereNotNull('plan_max_mt5_accounts_snapshot')
                ->orWhereNotNull('plan_capital_required_snapshot');
        })->exists();

        if ($snapshotsExist) {
            throw new LogicException('Subscription term snapshots are in use. Back them up and create a forward migration instead.');
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'plan_name_snapshot', 'plan_price_snapshot', 'plan_currency_snapshot',
                'plan_duration_days_snapshot', 'plan_max_mt5_accounts_snapshot', 'plan_capital_required_snapshot',
            ]);
        });
    }
};
