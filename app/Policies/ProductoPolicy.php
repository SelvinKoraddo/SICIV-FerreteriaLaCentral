<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;

class ProductoPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->rol, ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'], true);
    }

    public function update(User $user, Producto $producto): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Producto $producto): bool
    {
        return $this->create($user);
    }
}
