<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $salesQuery = Venta::query()->whereDate('fecha', $today);

        if ($request->user()->rol === 'VENDEDOR') {
            $salesQuery->where('id_usuario', $request->user()->id);
        }

        $salesToday = (clone $salesQuery)->get(['id_venta', 'total']);

        return response()->json([
            'sales_today' => [
                'total' => (float) $salesToday->sum('total'),
                'count' => $salesToday->count(),
            ],
            'out_of_stock' => Producto::query()
                ->where('activo', true)
                ->where('existencia_actual', '<=', 0)
                ->count(),
            'critical_stock' => Producto::query()
                ->where('activo', true)
                ->whereColumn('existencia_actual', '<=', 'existencia_minima')
                ->count(),
            'latest_movements' => MovimientoInventario::query()
                ->whereDate('fecha', $today)
                ->count(),
        ]);
    }
}
