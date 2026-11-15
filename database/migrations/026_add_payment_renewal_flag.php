<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->boolean('is_renewal')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->where('is_renewal', true)->exists()) {
            throw new LogicException('Renewal payment history exists. Preserve it and create a forward migration instead.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('is_renewal');
        });
    }
};
