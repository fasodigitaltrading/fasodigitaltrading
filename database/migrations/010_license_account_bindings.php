<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('license_account_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->char('account_hash', 64);
            $table->timestamp('first_validated_at');
            $table->timestamp('last_validated_at');
            $table->timestamps();
            $table->unique(['license_id', 'account_hash']);
            $table->index(['license_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_account_bindings');
    }
};
