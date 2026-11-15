<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brvm_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('symbol', 12);
            $table->string('security_name_snapshot', 255)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('limit_price', 16, 2)->nullable();
            $table->decimal('quote_price', 16, 2)->nullable();
            $table->timestamp('quote_at')->nullable();
            $table->string('quote_source', 120)->nullable();
            $table->string('status', 20)->default('RECEIVED');
            $table->timestamp('customer_acknowledged_at');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable();
            $table->string('coris_reference', 120)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        if (DB::table('brvm_purchase_requests')->exists()) {
            throw new LogicException('BRVM purchase request history exists. Back it up and create a forward migration instead.');
        }

        Schema::dropIfExists('brvm_purchase_requests');
    }
};
