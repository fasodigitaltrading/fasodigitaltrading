<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PrimaryAdministrator;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->isActive($actor)
            && ($actor->is($target) || $this->isVerifiedAdmin($actor));
    }

    public function update(User $actor, User $target): bool
    {
        return $this->isActive($actor)
            && ($actor->is($target) || $this->isVerifiedAdmin($actor));
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->isVerifiedAdmin($actor) && !$actor->is($target);
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
