<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class License extends Model
{
    protected $fillable = ['user_id', 'subscription_id', 'license_key', 'license_key_hash', 'legacy_license_key_encrypted', 'status', 'activated_at', 'expires_at', 'max_accounts', 'last_validation_at'];
    protected $hidden = ['license_key', 'license_key_hash', 'legacy_license_key_encrypted'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'expires_at' => 'datetime', 'last_validation_at' => 'datetime'];
    }

    public static function generateKey(): string
    {
        return 'BWT-'.strtoupper(Str::random(24));
    }

    /** Create a license and return the unpersisted key once for delivery to its owner. */
    public static function issue(array $attributes): array
    {
        $key = static::generateKey();
        $license = static::create(array_merge($attributes, [
            'license_key' => 'NXT-HASHED-'.Str::uuid(),
            'license_key_hash' => hash('sha256', $key),
            'legacy_license_key_encrypted' => Crypt::encryptString($key),
        ]));

        return [$license, $key];
    }

    public function revealKey(): ?string
    {
        if (!$this->legacy_license_key_encrypted) {
            return null;
        }

        return Crypt::decryptString($this->legacy_license_key_encrypted);
    }

    protected static function booted(): void
    {
        static::creating(function (License $license): void {
            if (empty($license->license_key_hash) && !empty($license->license_key)) {
                $plainKey = (string) $license->license_key;
                if (str_starts_with($plainKey, 'NXT-HASHED-')) {
                    throw new InvalidArgumentException('Issue licenses with License::issue() so their key is hashed before storage.');
                }
                $license->license_key_hash = hash('sha256', $plainKey);
                $license->legacy_license_key_encrypted = Crypt::encryptString($plainKey);
                $license->license_key = 'NXT-HASHED-'.Str::uuid();
            }
        });
    }

    public function accountBindings(): HasMany
    {
        return $this->hasMany(LicenseAccountBinding::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
