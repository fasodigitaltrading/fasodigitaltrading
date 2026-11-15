<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionOrder extends Model
{
    protected $fillable = [
        'user_id', 'subscription_plan_id', 'idempotency_key', 'status', 'plan_name_snapshot',
        'plan_description_snapshot', 'plan_features_snapshot',
        'amount_usd', 'duration_days_snapshot', 'max_mt5_accounts_snapshot', 'capital_required_snapshot',
        'amount_xof', 'fx_rate', 'fx_rate_as_of', 'fx_rate_updated_at', 'fx_provider', 'fx_source_url', 'fx_fetched_at',
        'quote_expires_at', 'estimated_daily_gain_usd_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'plan_features_snapshot' => 'array',
            'duration_days_snapshot' => 'integer',
            'max_mt5_accounts_snapshot' => 'integer',
            'capital_required_snapshot' => 'decimal:2',
            'amount_xof' => 'integer',
            'fx_rate' => 'decimal:4',
            'fx_rate_as_of' => 'date',
            'fx_rate_updated_at' => 'immutable_datetime',
            'fx_provider' => 'string',
            'fx_fetched_at' => 'datetime',
            'quote_expires_at' => 'datetime',
            'estimated_daily_gain_usd_snapshot' => 'decimal:2',
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

    public function manualPaymentSubmissions(): HasMany
    {
        return $this->hasMany(ManualPaymentSubmission::class, 'subscription_order_id');
    }

    public function manualPaymentOtps(): HasMany
    {
        return $this->hasMany(ManualPaymentOtp::class, 'subscription_order_id');
    }
}
