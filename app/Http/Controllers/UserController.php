<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * Listado de usuarios.
     */
    public function index(): JsonResponse
    {
        return response()->json(User::select(
            'id',
            'name',
            'email',
            'usuario',
            'rol',
            'activo'
        )
        ->orderBy('name')
        ->get());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json($user->only(['id', 'name', 'email', 'usuario', 'rol', 'activo']), 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user->fresh()->only(['id', 'name', 'email', 'usuario', 'rol', 'activo']));
    }

    public function toggleStatus(User $user): JsonResponse
    {
        if ($user->is(auth()->user())) {
            return response()->json(['message' => 'No puedes desactivar tu propio usuario.'], 422);
        }

        $user->update(['activo' => ! $user->activo]);

        return response()->json([
            'message' => $user->activo ? 'Usuario activado.' : 'Usuario desactivado.',
            'user' => $user->fresh()->only(['id', 'name', 'email', 'usuario', 'rol', 'activo']),
        ]);
    }
}
