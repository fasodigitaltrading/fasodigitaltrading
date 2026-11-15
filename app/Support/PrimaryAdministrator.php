<?php

namespace App\Support;

use App\Models\User;

final class PrimaryAdministrator
{
    public static function matches(?User $user): bool
    {
        return $user !== null
            && $user->role === 'ADMIN'
            && $user->status === 'ACTIVE'
            && hash_equals(
                (string) config('admin.email'),
                mb_strtolower(trim((string) $user->email)),
            );
    }

    public static function authenticated(?User $user): bool
    {
        return self::matches($user) && $user->mfa_verified_at !== null;
    }
}
