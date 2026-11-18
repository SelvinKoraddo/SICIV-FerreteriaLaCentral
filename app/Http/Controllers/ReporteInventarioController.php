<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultaInventarioRequest;
use App\Models\MovimientoInventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReporteInventarioController extends Controller
{
    public function mayorMovimiento(ConsultaInventarioRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        // Cuenta solo lo vendido y conserva los importes que se cobraron en cada venta.
        $vendidos = DB::table('detalles_venta as detalle')
            ->join('ventas as venta', 'venta.id_venta', '=', 'detalle.id_venta')
            ->where('venta.estado', 'CONFIRMADA')
            ->when($filtros['fecha_desde'] ?? null, fn ($query, $fecha) => $query->whereDate('venta.fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn ($query, $fecha) => $query->whereDate('venta.fecha', '<=', $fecha))
            ->select('detalle.id_producto')
            ->selectRaw('SUM(detalle.cantidad) as cantidad_vendida, SUM(detalle.subtotal) as monto_vendido')
            ->groupBy('detalle.id_producto');

        // Incluye productos desactivados porque sus ventas siguen formando parte del historial.
        $productos = DB::table('productos as producto')
            ->joinSub($vendidos, 'vendidos', fn ($join) => $join->on('vendidos.id_producto', '=', 'producto.id_producto'))
            ->join('categorias as categoria', 'categoria.id_categoria', '=', 'producto.id_categoria')
            ->when($filtros['categoria'] ?? null, fn ($query, $categoria) => $query->where('producto.id_categoria', $categoria))
            ->select('producto.id_producto', 'producto.codigo', 'producto.nombre', 'producto.unidad_medida', 'producto.existencia_actual', 'categoria.nombre as categoria', 'vendidos.cantidad_vendida', 'vendidos.monto_vendido')
            ->orderByDesc('vendidos.cantidad_vendida')
            ->orderBy('producto.codigo')
            ->limit($filtros['limite'] ?? 10)
            ->get();

        return response()->json(['data' => $productos]);
    }

    public function movimientos(ConsultaInventarioRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        // Muestra los saldos guardados; esta consulta no cambia las existencias.
        $movimientos = MovimientoInventario::query()
            ->with('producto')
            // El vendedor solo ve las salidas de las ventas que él registró.
            ->when($request->user()->rol === 'VENDEDOR', function ($query) use ($request) {
                $query->where('tipo', 'VENTA')
                    ->whereHas('detalleVenta.venta', fn ($venta) => $venta->where('id_usuario', $request->user()->id));
            })
            ->when($filtros['producto'] ?? null, fn ($query, $producto) => $query->where('id_producto', $producto))
            ->when($filtros['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->when($filtros['fecha_desde'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha', '<=', $fecha))
            ->orderBy('fecha')
            ->orderBy('id_movimiento')
            ->paginate($filtros['per_page'] ?? 25)
            ->through(function (MovimientoInventario $movimiento): array {
                return [
                    'id_movimiento' => $movimiento->id_movimiento,
                    'fecha' => $movimiento->fecha?->toIso8601String(),
                    'codigo' => $movimiento->producto?->codigo,
                    'producto' => $movimiento->producto?->nombre,
                    'unidad_medida' => $movimiento->producto?->unidad_medida,
                    'tipo' => $movimiento->tipo,
                    'cantidad' => $movimiento->cantidad,
                    'saldo_anterior' => $movimiento->saldo_anterior,
                    'saldo_resultante' => $movimiento->saldo_resultante,
                    'referencia' => $movimiento->referencia,
                ];
            });

        return response()->json($movimientos);
    }
}
