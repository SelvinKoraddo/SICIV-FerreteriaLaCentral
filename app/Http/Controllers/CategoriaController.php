<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Categoria::withCount('productos')->orderBy('nombre')->get());
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        return response()->json(Categoria::create($request->validated()), 201);
    }

    public function update(StoreCategoriaRequest $request, Categoria $categoria): JsonResponse
    {
        $categoria->update($request->validated());

        return response()->json($categoria->loadCount('productos'));
    }

    public function toggleStatus(Categoria $categoria): JsonResponse
    {
        $this->authorize('manage', $categoria);
        $categoria->update(['activa' => ! $categoria->activa]);

        return response()->json([
            'message' => $categoria->activa ? 'Categoría activada.' : 'Categoría desactivada.',
            'categoria' => $categoria->loadCount('productos'),
        ]);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        $this->authorize('delete', $categoria);
        $categoria->update(['activa' => false]);

        return response()->json(['message' => 'Categoría desactivada.']);
    }
}
