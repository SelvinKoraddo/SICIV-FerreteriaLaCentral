<?php

namespace App\Policies;

use App\Models\User;

class VentaPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->rol, ['ADMINISTRADOR', 'VENDEDOR'], true);
    }
}
