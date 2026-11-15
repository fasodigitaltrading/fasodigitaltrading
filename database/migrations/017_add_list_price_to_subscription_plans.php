<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('list_price', 12, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        if (DB::table('subscription_plans')->whereNotNull('list_price')->exists()) {
            throw new LogicException('List prices are in use. Back up the catalogue and create a forward migration instead.');
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('list_price');
        });
    }
};
