<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleEntrada extends Model
{
    protected $table = 'detalles_entrada';

    protected $primaryKey = 'id_detalle_entrada';

    public $timestamps = false;

    protected $fillable = ['cantidad', 'costo_unitario', 'subtotal', 'id_entrada', 'id_producto'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(EntradaInventario::class, 'id_entrada', 'id_entrada');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
