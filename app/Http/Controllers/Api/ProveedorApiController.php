<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->query('buscar'));

        $proveedores = Proveedor::query()
            ->with(['entradas.detalles.producto'])
            ->when(
                $buscar !== '',
                fn ($query) => $query->where(
                    fn ($subquery) => $subquery
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('nit', 'like', "%{$buscar}%")
                        ->orWhere('correo', 'like', "%{$buscar}%")
                )
            )
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => $proveedores]);
    }

    public function store(StoreProveedorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['activo'] = true;

        return response()->json(
            Proveedor::create($data),
            201
        );
    }

    public function estado(Proveedor $proveedor): JsonResponse
    {
        $proveedor->update([
            'activo' => ! $proveedor->activo,
        ]);

        return response()->json($proveedor->fresh());
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor): JsonResponse
    {
        $proveedor->update($request->validated());

        return response()->json($proveedor->fresh());
    }
}