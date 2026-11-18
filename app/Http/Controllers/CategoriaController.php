<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoriaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Categoria::withCount('productos')->orderBy('nombre')->get());
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        // Incluye el estado inicial que se guardó al crear la categoría.
        $categoria = Categoria::create($request->validated())->refresh()->loadCount('productos');

        return response()->json([
            'message' => 'Categoría registrada correctamente.',
            'categoria' => $categoria,
        ], 201);
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria): JsonResponse
    {
        $categoria->update($request->validated());

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'categoria' => $categoria->loadCount('productos'),
        ]);
    }

    public function cambiarEstado(Request $request, Categoria $categoria): JsonResponse
    {
        $validated = $request->validate(['activa' => ['required', 'boolean']]);

        if (! $validated['activa']) {
            Gate::authorize('delete', $categoria);
        }

        $categoria->update(['activa' => $validated['activa']]);
        $estado = $categoria->activa ? 'activada' : 'desactivada';

        return response()->json([
            'message' => "Categoría {$estado} correctamente.",
            'categoria' => $categoria->loadCount('productos'),
        ]);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        Gate::authorize('delete', $categoria);
        $categoria->update(['activa' => false]);

        return response()->json(['message' => 'Categoría desactivada.']);
    }
}
