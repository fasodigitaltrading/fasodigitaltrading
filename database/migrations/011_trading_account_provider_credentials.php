<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->string('provider_account_id', 64)->nullable()->unique();
            $table->text('account_access_token')->nullable();
        });

        Schema::table('open_positions', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable();
            $table->unique(['trading_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('open_positions', function (Blueprint $table) {
            $table->dropUnique(['trading_account_id', 'external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->dropUnique(['provider_account_id']);
            $table->dropColumn(['provider_account_id', 'account_access_token']);
        });
    }
};
