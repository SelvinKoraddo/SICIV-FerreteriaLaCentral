<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticación por sesión para la aplicación Vue.
 *
 * @see HU-01 Inicio de sesión
 */
class AuthController extends Controller
{
    /**
     * Inicia sesión y regenera el identificador de sesión (previene fijación de sesión).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'user' => $this->perfil($request->user()),
        ]);
    }

    /**
     * Cierra la sesión e invalida la cookie y el token CSRF vigentes.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Devuelve el usuario autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->perfil($request->user())]);
    }

    /**
     * Datos del usuario que necesita el frontend; omite cualquier campo sensible.
     *
     * @return array{id: int, name: string, usuario: string, rol: string}
     */
    private function perfil(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'usuario' => $usuario->usuario,
            'rol' => $usuario->rol,
        ];
    }
}
