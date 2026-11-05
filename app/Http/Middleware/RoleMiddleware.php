<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && $user->activo && in_array($user->rol, $roles, true), 403, 'No tienes permisos para realizar esta acción.');

        return $next($request);
    }
}
