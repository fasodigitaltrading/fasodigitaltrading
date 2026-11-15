<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;
use App\Support\PrimaryAdministrator;

class LicensePolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    public function view(User $actor, License $license): bool
    {
        return $this->isActive($actor)
            && ($license->user_id === $actor->id || $this->isVerifiedAdmin($actor));
    }

    public function create(User $actor): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    public function update(User $actor, License $license): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    public function delete(User $actor, License $license): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    private function isVerifiedAdmin(User $user): bool
    {
        return PrimaryAdministrator::authenticated($user);
    }

    private function isActive(User $user): bool
    {
        return $user->status === 'ACTIVE';
    }
}
