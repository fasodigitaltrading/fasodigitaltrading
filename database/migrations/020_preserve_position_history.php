<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('open_positions', function (Blueprint $table) {
            $table->boolean('is_open')->default(true);
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });

        Schema::create('position_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->constrained()->restrictOnDelete();
            $table->string('external_id', 100)->nullable();
            $table->string('symbol', 30);
            $table->enum('type', ['BUY', 'SELL']);
            $table->decimal('volume', 18, 4);
            $table->decimal('entry_price', 20, 8)->nullable();
            $table->decimal('current_price', 20, 8)->nullable();
            $table->decimal('floating_profit_loss', 18, 2)->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->boolean('is_open')->default(true);
            $table->dateTime('captured_at');
            $table->timestamps();
            $table->index(['trading_account_id', 'external_id', 'captured_at'], 'position_snapshots_account_external_captured_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('position_snapshots')->exists()
            || DB::table('open_positions')->where('is_open', false)->orWhereNotNull('closed_at')->exists()) {
            throw new LogicException('Position history exists. Back it up and create a forward migration instead.');
        }

        Schema::dropIfExists('position_snapshots');
        Schema::table('open_positions', function (Blueprint $table) {
            $table->dropColumn(['is_open', 'closed_at', 'last_seen_at']);
        });
    }
};
