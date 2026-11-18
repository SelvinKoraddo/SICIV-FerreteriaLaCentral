<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct(private readonly VentaService $service) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'numero' => ['nullable', 'string'],
            'vendedor' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Venta::with('detalles.producto', 'usuario')->latest('id_venta');

        if ($request->user()->rol === 'VENDEDOR') {
            $query->where('id_usuario', $request->user()->id);
        } elseif ($request->filled('vendedor')) {
            $query->where('id_usuario', $request->integer('vendedor'));
        }

        if (!empty($validated['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $validated['fecha_desde']);
        }

        if (!empty($validated['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $validated['fecha_hasta']);
        }

        if (!empty($validated['numero'])) {
            $query->where('numero', 'like', '%'.trim($validated['numero']).'%');
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 15)));
    }

    public function store(StoreVentaRequest $request): JsonResponse
    {
        return response()->json($this->service->registrar($request->user(), $request->validated()), 201);
    }

    public function show(Request $request, Venta $venta): JsonResponse
    {
        abort_if($request->user()->rol === 'VENDEDOR' && $venta->id_usuario !== $request->user()->id, 403);

        return response()->json($venta->load('detalles.producto', 'usuario'));
    }
}
