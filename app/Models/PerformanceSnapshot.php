<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceSnapshot extends Model
{
    protected $fillable = ['trading_account_id', 'captured_at', 'balance', 'equity', 'profit_loss', 'drawdown'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'balance' => 'decimal:2', 'equity' => 'decimal:2', 'profit_loss' => 'decimal:2', 'drawdown' => 'decimal:2'];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }
}
