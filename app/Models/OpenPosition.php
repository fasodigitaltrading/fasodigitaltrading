<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenPosition extends Model
{
    protected $fillable = [
        'trading_account_id', 'external_id', 'symbol', 'type', 'volume', 'entry_price',
        'current_price', 'floating_profit_loss', 'opened_at', 'is_open', 'closed_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'decimal:4', 'entry_price' => 'decimal:8', 'current_price' => 'decimal:8',
            'opened_at' => 'datetime', 'is_open' => 'boolean', 'closed_at' => 'datetime', 'last_seen_at' => 'datetime',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }
}
