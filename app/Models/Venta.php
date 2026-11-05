<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $primaryKey = 'id_venta';

    public $timestamps = false;

    protected $fillable = ['numero', 'fecha', 'total', 'estado', 'id_usuario'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'total' => 'decimal:2'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }

    public function calcularTotal(): float
    {
        return (float) $this->detalles->sum(fn (DetalleVenta $detalle): float => (float) $detalle->subtotal);
    }
}
