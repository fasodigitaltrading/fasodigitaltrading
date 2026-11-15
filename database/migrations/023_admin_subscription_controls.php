<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('admin_override', 20)->nullable();
            $table->timestamp('admin_controlled_at')->nullable();
            $table->foreignId('admin_controlled_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('subscriptions')->whereNotNull('admin_override')
            ->orWhereNotNull('admin_controlled_at')->orWhereNotNull('admin_controlled_by')->exists()) {
            throw new LogicException('Subscription control history is in use. Back it up and create a forward migration instead.');
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_controlled_by');
            $table->dropColumn(['admin_override', 'admin_controlled_at']);
        });
    }
};
