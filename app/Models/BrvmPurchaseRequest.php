<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrvmPurchaseRequest extends Model
{
    public const STATUSES = ['RECEIVED', 'FORWARDED', 'ACCEPTED', 'REJECTED', 'EXECUTED', 'CANCELLED'];

    protected $fillable = [
        'user_id', 'symbol', 'security_name_snapshot', 'quantity', 'limit_price',
        'quote_price', 'quote_at', 'quote_source', 'status', 'customer_acknowledged_at',
        'handled_by', 'forwarded_at', 'coris_reference', 'admin_note', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'limit_price' => 'decimal:2',
            'quote_price' => 'decimal:2',
            'quote_at' => 'datetime',
            'customer_acknowledged_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
