<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('capital_required', 12, 2)->default(0);
            $table->decimal('lot_size', 8, 3)->default(0.001);
            $table->decimal('projected_daily_gain', 12, 2)->default(0);
            $table->boolean('vps_included')->default(false);
            $table->unsignedSmallInteger('trial_hours')->default(0);
        });

        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('intent', 40)->default('question');
            $table->string('package', 64)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('NEW');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['capital_required', 'lot_size', 'projected_daily_gain', 'vps_included', 'trial_hours']);
        });
    }
};
