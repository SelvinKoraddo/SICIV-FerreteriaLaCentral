<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function movimientos(Request $request): JsonResponse
    {
        $query = MovimientoInventario::with('producto:id_producto,codigo,nombre,unidad_medida')
            ->latest('fecha')->latest('id_movimiento');

        if ($request->filled('producto')) {
            $query->where('id_producto', $request->integer('producto'));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->string('tipo'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->date('hasta'));
        }

        return response()->json($query->paginate(min(max($request->integer('per_page', 100), 1), 500)));
    }

    public function productosMayorMovimiento(Request $request): JsonResponse
    {
        $query = DB::table('detalles_venta')
            ->join('ventas', 'ventas.id_venta', '=', 'detalles_venta.id_venta')
            ->join('productos', 'productos.id_producto', '=', 'detalles_venta.id_producto')
            ->select([
                'productos.id_producto',
                'productos.codigo',
                'productos.nombre',
                'productos.unidad_medida',
                DB::raw('SUM(detalles_venta.cantidad) AS unidades_vendidas'),
                DB::raw('SUM(detalles_venta.subtotal) AS monto_recaudado'),
            ])
            ->where('ventas.estado', 'CONFIRMADA')
            ->groupBy('productos.id_producto', 'productos.codigo', 'productos.nombre', 'productos.unidad_medida')
            ->orderByDesc('unidades_vendidas');

        if ($request->filled('desde')) {
            $query->whereDate('ventas.fecha', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('ventas.fecha', '<=', $request->date('hasta'));
        }
        if ($request->filled('categoria')) {
            $query->where('productos.id_categoria', $request->integer('categoria'));
        }

        return response()->json($query->limit(min(max($request->integer('limite', 10), 1), 50))->get());
    }
}
