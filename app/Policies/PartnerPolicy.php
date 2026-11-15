<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;
use App\Support\PrimaryAdministrator;

class PartnerPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    public function view(User $actor, Partner $partner): bool
    {
        return $actor->status === 'ACTIVE'
            && ($partner->user_id === $actor->id || $this->isVerifiedAdmin($actor));
    }

    public function create(User $actor): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    public function update(User $actor, Partner $partner): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    public function delete(User $actor, Partner $partner): bool
    {
        return $this->isVerifiedAdmin($actor);
    }

    private function isVerifiedAdmin(User $user): bool
    {
        return PrimaryAdministrator::authenticated($user);
    }
}
