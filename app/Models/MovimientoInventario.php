<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $primaryKey = 'id_movimiento';

    public $timestamps = false;

    protected $fillable = [
        'fecha', 'tipo', 'cantidad', 'saldo_anterior', 'saldo_resultante',
        'referencia', 'id_producto', 'id_detalle_venta', 'id_detalle_entrada',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'cantidad' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_resultante' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function detalleVenta(): BelongsTo
    {
        return $this->belongsTo(DetalleVenta::class, 'id_detalle_venta', 'id_detalle_venta');
    }

    public function detalleEntrada(): BelongsTo
    {
        return $this->belongsTo(DetalleEntrada::class, 'id_detalle_entrada', 'id_detalle_entrada');
    }
}
