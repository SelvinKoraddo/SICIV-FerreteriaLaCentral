<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;

class CategoriaPolicy
{
    public function manage(User $user): bool
    {
        return in_array($user->rol, ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'], true);
    }

    public function delete(User $user, Categoria $categoria): bool
    {
        return $this->manage($user) && ! $categoria->productos()
            ->where(function ($query): void {
                $query->where('activo', true)
                    ->orWhereHas('detallesEntrada')
                    ->orWhereHas('detallesVenta');
            })
            ->exists();
    }
}
