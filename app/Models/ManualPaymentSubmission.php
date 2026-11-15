<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentSubmission extends Model
{
    protected $fillable = [
        'subscription_order_id', 'user_id', 'payment_id', 'method', 'recipient_snapshot',
        'expected_amount', 'currency', 'transaction_reference', 'proof_path', 'proof_sha256',
        'status', 'review_note', 'reviewed_by', 'reviewed_at', 'customer_acknowledged_at', 'customer_terms_version',
        'manual_payment_otp_id', 'declared_amount', 'transaction_occurred_at', 'admin_email_status',
        'admin_email_sent_at', 'admin_email_last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'declared_amount' => 'decimal:2',
            'reviewed_at' => 'immutable_datetime',
            'customer_acknowledged_at' => 'immutable_datetime',
            'transaction_occurred_at' => 'immutable_datetime',
            'admin_email_sent_at' => 'immutable_datetime',
            'admin_email_last_attempt_at' => 'immutable_datetime',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function otp(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentOtp::class, 'manual_payment_otp_id');
    }
}
