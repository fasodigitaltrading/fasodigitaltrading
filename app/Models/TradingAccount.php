<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingAccount extends Model
{
    protected $fillable = [
        'user_id', 'broker_id', 'account_number_masked', 'server', 'connection_status', 'readonly_status',
        'balance', 'equity', 'profit', 'daily_profit', 'total_profit', 'currency', 'last_sync_at',
        'provider_account_id', 'account_access_token',
    ];

    protected $hidden = ['account_access_token'];

    protected function casts(): array
    {
        return [
            'account_access_token' => 'encrypted',
            'readonly_status' => 'boolean',
            'balance' => 'decimal:2', 'equity' => 'decimal:2', 'profit' => 'decimal:2',
            'daily_profit' => 'decimal:2', 'total_profit' => 'decimal:2', 'last_sync_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(OpenPosition::class);
    }

    public function openPositions(): HasMany
    {
        return $this->hasMany(OpenPosition::class)->where('is_open', true);
    }

    public function positionSnapshots(): HasMany
    {
        return $this->hasMany(PositionSnapshot::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function performanceSnapshots(): HasMany
    {
        return $this->hasMany(PerformanceSnapshot::class);
    }
}
