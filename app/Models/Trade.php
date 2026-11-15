<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = [
        'trading_account_id', 'external_id', 'symbol', 'type', 'volume',
        'entry_price', 'exit_price', 'profit_loss', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['volume' => 'decimal:4', 'entry_price' => 'decimal:8', 'exit_price' => 'decimal:8', 'profit_loss' => 'decimal:2', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }
}
