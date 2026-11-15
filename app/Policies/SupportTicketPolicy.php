<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\PrimaryAdministrator;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'ACTIVE'
            && ($user->role !== 'ADMIN' || PrimaryAdministrator::authenticated($user));
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $this->viewAny($user) && ($ticket->user_id === $user->id || $user->role === 'ADMIN');
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket) && $ticket->status !== 'CLOSED';
    }
}
