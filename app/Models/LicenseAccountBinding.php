<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseAccountBinding extends Model
{
    protected $fillable = ['license_id', 'account_hash', 'first_validated_at', 'last_validated_at'];

    protected function casts(): array
    {
        return ['first_validated_at' => 'datetime', 'last_validated_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
