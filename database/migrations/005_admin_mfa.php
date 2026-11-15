<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mfa_code_hash')->nullable()->after('two_factor_enabled');
            $table->timestamp('mfa_code_expires_at')->nullable()->after('mfa_code_hash');
            $table->timestamp('mfa_verified_at')->nullable()->after('mfa_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_code_hash', 'mfa_code_expires_at', 'mfa_verified_at']);
        });
    }
};
