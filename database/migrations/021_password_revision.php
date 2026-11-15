<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('password_revision')->default(0);
        });
    }

    public function down(): void
    {
        if (DB::table('users')->exists()) {
            throw new LogicException('User accounts exist. Create a forward migration instead of dropping account security state.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_revision');
        });
    }
};
