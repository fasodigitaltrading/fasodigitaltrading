<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 2)->default(10)->change();
        });

        // Existing default-rate partners move to the requested 10%; historical
        // commission amounts and percentage snapshots are left untouched.
        DB::table('partners')->where('commission_rate', '5.00')->update([
            'commission_rate' => '10.00',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('partners')->where('commission_rate', '10.00')->exists()) {
            throw new LogicException('10% partner rates are in use. Preserve the user-approved commission rate and create a forward migration instead.');
        }

        Schema::table('partners', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 2)->default(5)->change();
        });
    }
};
