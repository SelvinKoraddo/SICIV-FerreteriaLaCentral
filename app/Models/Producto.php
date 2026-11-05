<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'unidad_medida', 'precio_venta',
        'existencia_actual', 'existencia_minima', 'activo', 'id_categoria',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:2',
            'existencia_actual' => 'decimal:2',
            'existencia_minima' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function detallesEntrada(): HasMany
    {
        return $this->hasMany(DetalleEntrada::class, 'id_producto', 'id_producto');
    }

    public function detallesVenta(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_producto', 'id_producto');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'id_producto', 'id_producto');
    }

    public function descontarStock(float $cantidad): void
    {
        if ($cantidad <= 0 || $cantidad > (float) $this->existencia_actual) {
            throw new \DomainException('La cantidad solicitada no es válida para la existencia actual.');
        }

        $this->existencia_actual -= $cantidad;
    }

    public function estaBajoMinimo(): bool
    {
        return (float) $this->existencia_actual <= (float) $this->existencia_minima;
    }
}
