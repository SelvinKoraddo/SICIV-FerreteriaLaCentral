<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaService
{
    public function __construct(private readonly ProductoService $productoService) {}

    public function registrar(User $usuario, array $datos): Venta
    {
        return DB::transaction(function () use ($usuario, $datos): Venta {
            $total = 0;
            $detalles = [];

            foreach ($datos['detalles'] as $detalle) {
                $producto = Producto::query()->findOrFail($detalle['id_producto']);
                $cantidad = (float) $detalle['cantidad'];
                $productoActualizado = $this->productoService->descontarStock($producto, $cantidad);
                $subtotal = $cantidad * (float) $producto->precio_venta;
                $total += $subtotal;
                $detalles[] = [
                    'producto' => $productoActualizado,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $producto->precio_venta,
                    'subtotal' => $subtotal,
                ];
            }

            $venta = Venta::create([
                'numero' => $datos['numero'] ?? $this->siguienteNumero(),
                'fecha' => $datos['fecha'],
                'total' => $total,
                'estado' => 'CONFIRMADA',
                'id_usuario' => $usuario->id,
            ]);

            foreach ($detalles as $detalle) {
                $detalleVenta = $venta->detalles()->create([
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $detalle['subtotal'],
                    'id_producto' => $detalle['producto']->id_producto,
                ]);

                $detalle['producto']->movimientos()->create([
                    'fecha' => now(),
                    'tipo' => 'VENTA',
                    'cantidad' => $detalle['cantidad'],
                    'saldo_anterior' => (float) $detalle['producto']->existencia_actual + $detalle['cantidad'],
                    'saldo_resultante' => $detalle['producto']->existencia_actual,
                    'referencia' => 'Venta '.$venta->numero,
                    'id_detalle_venta' => $detalleVenta->id_detalle_venta,
                ]);

                if ($detalle['producto']->estaBajoMinimo()) {
                    Log::warning('Producto con existencia bajo mínimo.', [
                        'id_producto' => $detalle['producto']->id_producto,
                        'existencia_actual' => $detalle['producto']->existencia_actual,
                        'existencia_minima' => $detalle['producto']->existencia_minima,
                    ]);
                }
            }

            return $venta->load('detalles.producto');
        });
    }

    private function siguienteNumero(): string
    {
        $ultimoNumero = Venta::query()
            ->lockForUpdate()
            ->latest('id_venta')
            ->value('numero');

        preg_match('/(\d+)$/', (string) $ultimoNumero, $coincidencia);
        $siguiente = ((int) ($coincidencia[1] ?? 0)) + 1;

        return 'VTA-'.str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
    }
}
