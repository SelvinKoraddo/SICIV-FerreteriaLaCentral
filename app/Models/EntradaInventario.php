<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntradaInventario extends Model
{
    protected $table = 'entradas_inventario';

    protected $primaryKey = 'id_entrada';

    public $timestamps = false;

    protected $fillable = ['numero', 'fecha', 'referencia', 'total', 'estado', 'id_proveedor', 'id_usuario'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'total' => 'decimal:2'];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleEntrada::class, 'id_entrada', 'id_entrada');
    }
}
