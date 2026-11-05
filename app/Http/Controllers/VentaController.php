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
        $query = Venta::with('detalles.producto', 'usuario')->latest('id_venta');

        if ($request->user()->rol === 'VENDEDOR') {
            $query->where('id_usuario', $request->user()->id);
        } elseif ($request->filled('vendedor')) {
            $query->where('id_usuario', $request->integer('vendedor'));
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->date('fecha'));
        }

        if ($request->filled('numero')) {
            $query->where('numero', 'like', '%'.$request->string('numero')->trim().'%');
        }

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
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
