<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_totp_last_step')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('admin_totp_last_step')->exists()) {
            throw new LogicException('Administrator TOTP use history exists. Preserve it and create a forward migration instead.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_totp_last_step');
        });
    }
};
