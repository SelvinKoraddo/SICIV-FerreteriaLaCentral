<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $primaryKey = 'id_proveedor';

    public $timestamps = false;

    protected $fillable = ['nombre', 'nit', 'telefono', 'correo', 'direccion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(EntradaInventario::class, 'id_proveedor', 'id_proveedor');
    }
}
