<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'subscription_id', 'subscription_order_id', 'provider', 'transaction_id', 'amount', 'currency',
        'status', 'paid_at', 'metadata', 'checkout_url', 'idempotency_key',
        'is_renewal',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'metadata' => 'array', 'is_renewal' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionOrder(): BelongsTo
    {
        return $this->belongsTo(SubscriptionOrder::class);
    }
}
