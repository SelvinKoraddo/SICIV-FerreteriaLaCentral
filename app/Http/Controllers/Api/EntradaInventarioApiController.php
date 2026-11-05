<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntradaRequest;
use App\Models\DetalleEntrada;
use App\Models\EntradaInventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntradaInventarioApiController extends Controller
{
    public function catalogos(): JsonResponse
    {
        return response()->json([
            'proveedores' => Proveedor::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'productos' => Producto::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(StoreEntradaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $usuarioId = $request->user()->getAuthIdentifier();

        $entrada = DB::transaction(function () use ($data, $usuarioId) {
            $entrada = EntradaInventario::create([
                'numero' => 'ENT'
                    . now()->format('ymdHis')
                    . Str::upper(Str::random(3)),
                'fecha' => $data['fecha'],
                'referencia' => $data['referencia'] ?? null,
                'total' => 0,
                'estado' => 'CONFIRMADA',
                'id_proveedor' => $data['id_proveedor'],
                'id_usuario' => $usuarioId,
            ]);

            $total = 0;

            foreach ($data['detalles'] as $item) {
                $producto = Producto::query()
                    ->whereKey($item['id_producto'])
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                $cantidad = round((float) $item['cantidad'], 2);
                $costo = round((float) $item['costo_unitario'], 2);
                $subtotal = round($cantidad * $costo, 2);
                $saldoAnterior = round(
                    (float) $producto->existencia_actual,
                    2
                );
                $saldoResultante = round(
                    $saldoAnterior + $cantidad,
                    2
                );

                $detalle = DetalleEntrada::create([
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'subtotal' => $subtotal,
                    'id_entrada' => $entrada->id_entrada,
                    'id_producto' => $producto->id_producto,
                ]);

                $producto->update([
                    'existencia_actual' => $saldoResultante,
                ]);

                MovimientoInventario::create([
                    'fecha' => $data['fecha'],
                    'tipo' => 'RECEPCION',
                    'cantidad' => $cantidad,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_resultante' => $saldoResultante,
                    'referencia' => $entrada->numero,
                    'id_producto' => $producto->id_producto,
                    'id_detalle_venta' => null,
                    'id_detalle_entrada' => $detalle->id_detalle_entrada,
                ]);

                $total += $subtotal;
            }

            $entrada->update([
                'total' => round($total, 2),
            ]);

            return $entrada;
        });

        return response()->json([
            'numero' => $entrada->numero,
        ], 201);
    }
}