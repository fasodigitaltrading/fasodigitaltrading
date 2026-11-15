<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commission extends Model
{
    protected $fillable = ['partner_id', 'user_id', 'payment_id', 'amount', 'currency', 'percentage', 'status', 'available_at', 'payout_reference', 'paid_at', 'paid_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'percentage' => 'decimal:2', 'available_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
