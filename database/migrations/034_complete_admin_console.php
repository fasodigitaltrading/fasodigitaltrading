<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->softDeletes());
        }

        if (!Schema::hasColumn('commissions', 'payout_reference')) {
            Schema::table('commissions', fn (Blueprint $table) => $table->string('payout_reference', 191)->nullable());
        }
        if (!Schema::hasColumn('commissions', 'paid_at')) {
            Schema::table('commissions', fn (Blueprint $table) => $table->timestamp('paid_at')->nullable());
        }
        if (!Schema::hasColumn('commissions', 'paid_by')) {
            Schema::table('commissions', fn (Blueprint $table) => $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete());
        }

        if (!Schema::hasColumn('support_tickets', 'category')) {
            Schema::table('support_tickets', fn (Blueprint $table) => $table->string('category', 40)->nullable());
        }
        if (!Schema::hasColumn('support_tickets', 'priority')) {
            Schema::table('support_tickets', fn (Blueprint $table) => $table->string('priority', 16)->default('NORMAL'));
        }
        if (!Schema::hasColumn('support_tickets', 'assigned_admin_id')) {
            Schema::table('support_tickets', fn (Blueprint $table) => $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete());
        }
        if (!Schema::hasColumn('support_tickets', 'resolved_at')) {
            Schema::table('support_tickets', fn (Blueprint $table) => $table->timestamp('resolved_at')->nullable());
        }

        if (!Schema::hasColumn('support_messages', 'is_internal')) {
            Schema::table('support_messages', fn (Blueprint $table) => $table->boolean('is_internal')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commissions') && Schema::hasColumn('commissions', 'payout_reference')) {
            $hasPayoutData = DB::table('commissions')->where(function ($query): void {
                $query->whereNotNull('payout_reference')->orWhereNotNull('paid_at')->orWhereNotNull('paid_by');
            })->exists();
            if ($hasPayoutData) {
                throw new LogicException('Commission payout records exist; preserve them and create a forward migration.');
            }
        }
        if (Schema::hasTable('support_tickets') && Schema::hasColumn('support_tickets', 'category')) {
            $hasWorkflowData = DB::table('support_tickets')->where(function ($query): void {
                $query->whereNotNull('category')->orWhere('priority', '!=', 'NORMAL')
                    ->orWhereNotNull('assigned_admin_id')->orWhereNotNull('resolved_at');
            })->exists();
            if ($hasWorkflowData) {
                throw new LogicException('Support workflow history exists; preserve it and create a forward migration.');
            }
        }
        if (Schema::hasTable('support_messages') && Schema::hasColumn('support_messages', 'is_internal')
            && DB::table('support_messages')->where('is_internal', true)->exists()) {
            throw new LogicException('Internal support notes exist; preserve them and create a forward migration.');
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'deleted_at')
            && DB::table('users')->whereNotNull('deleted_at')->exists()) {
            throw new LogicException('Soft-deleted user records exist; preserve them and create a forward migration.');
        }

        if (Schema::hasColumn('support_messages', 'is_internal')) {
            Schema::table('support_messages', fn (Blueprint $table) => $table->dropColumn('is_internal'));
        }
        if (Schema::hasColumn('support_tickets', 'assigned_admin_id')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('assigned_admin_id');
            });
        }
        $ticketColumns = array_values(array_filter(['category', 'priority', 'resolved_at'], fn ($column) => Schema::hasColumn('support_tickets', $column)));
        if ($ticketColumns !== []) {
            Schema::table('support_tickets', fn (Blueprint $table) => $table->dropColumn($ticketColumns));
        }
        if (Schema::hasColumn('commissions', 'paid_by')) {
            Schema::table('commissions', fn (Blueprint $table) => $table->dropConstrainedForeignId('paid_by'));
        }
        $commissionColumns = array_values(array_filter(['payout_reference', 'paid_at'], fn ($column) => Schema::hasColumn('commissions', $column)));
        if ($commissionColumns !== []) {
            Schema::table('commissions', fn (Blueprint $table) => $table->dropColumn($commissionColumns));
        }
        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
