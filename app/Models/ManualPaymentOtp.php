<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManualPaymentOtp extends Model
{
    protected $fillable = [
        'subscription_order_id', 'user_id', 'method', 'recipient_snapshot', 'expected_amount', 'currency',
        'code_hash', 'code_ciphertext', 'failed_attempts', 'expires_at', 'used_at', 'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'failed_attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubscriptionOrder::class, 'subscription_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): HasOne
    {
        return $this->hasOne(ManualPaymentSubmission::class, 'manual_payment_otp_id');
    }
}
