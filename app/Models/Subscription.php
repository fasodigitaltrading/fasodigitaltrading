<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'subscription_plan_id', 'status', 'starts_at', 'ends_at',
        'plan_name_snapshot', 'plan_price_snapshot', 'plan_currency_snapshot',
        'plan_duration_days_snapshot', 'plan_max_mt5_accounts_snapshot', 'plan_capital_required_snapshot',
        'admin_override', 'admin_controlled_at', 'admin_controlled_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'plan_price_snapshot' => 'decimal:2', 'plan_capital_required_snapshot' => 'decimal:2',
            'plan_duration_days_snapshot' => 'integer', 'plan_max_mt5_accounts_snapshot' => 'integer',
            'admin_controlled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function adminController(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_controlled_by');
    }

    public function isOperationallyActive(): bool
    {
        return $this->admin_override !== 'DISABLED'
            && $this->status === 'ACTIVE'
            && (!$this->starts_at || $this->starts_at->lte(now()))
            && (!$this->ends_at || $this->ends_at->isFuture());
    }
}
