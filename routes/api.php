<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExistenciaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\Api\ProveedorApiController;
use App\Http\Controllers\Api\EntradaInventarioApiController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// El LoginRequest limita intentos por usuario e IP; este throttle agrega un tope global por IP.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware('auth')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::middleware('role:ADMINISTRADOR')->group(function (): void {
        Route::get('/usuarios', [UserController::class, 'index']);
        Route::post('/usuarios', [UserController::class, 'store']);
        Route::put('/usuarios/{user}', [UserController::class, 'update']);
        Route::patch('/usuarios/{user}/estado', [UserController::class, 'toggleStatus']);
    });
    
    Route::get('/categorias', [CategoriaController::class, 'index']);
    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos/unidades-medida', [ProductoController::class, 'unidadesMedida']);
    // Debe registrarse antes de /productos/{producto} para no confundirse con un id.
    Route::get('/productos/exportar', [ProductoController::class, 'exportar'])
        ->middleware('role:ADMINISTRADOR,ENCARGADO_INVENTARIO');
    Route::get('/productos/{producto}', [ProductoController::class, 'show']);

    // HU-07: consulta de existencias (los tres roles, RN-09)
    Route::get('/existencias', [ExistenciaController::class, 'index'])->name('existencias.index');
    Route::get('/existencias/resumen', [ExistenciaController::class, 'resumen'])->name('existencias.resumen');

    // HU-08: alertas de existencia baja y agotados (los tres roles, RN-09)
    Route::get('/alertas', [AlertaController::class, 'index']);
    Route::get('/alertas/conteo', [AlertaController::class, 'conteo']);

    Route::get('/ventas', [VentaController::class, 'index']);
    Route::get('/ventas/{venta}', [VentaController::class, 'show']);
    Route::get('/reportes/movimientos', [ReporteController::class, 'movimientos']);
    Route::get('/reportes/productos-mayor-movimiento', [ReporteController::class, 'productosMayorMovimiento']);

    Route::middleware('role:ADMINISTRADOR,ENCARGADO_INVENTARIO')->group(function (): void {
        Route::post('/categorias', [CategoriaController::class, 'store']);
        Route::put('/categorias/{categoria}', [CategoriaController::class, 'update']);
        Route::patch('/categorias/{categoria}/estado', [CategoriaController::class, 'toggleStatus']);
        // HU-03: registro y edición de productos del catálogo
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{producto}', [ProductoController::class, 'update']);
        Route::patch('/productos/{producto}/estado', [ProductoController::class, 'cambiarEstado']);
        // HU-05: Gestión de proveedores
        Route::get('/proveedores', [ProveedorApiController::class, 'index']);
        Route::post('/proveedores', [ProveedorApiController::class, 'store']);
        Route::put('/proveedores/{proveedor}', [ProveedorApiController::class, 'update']);
        Route::patch('/proveedores/{proveedor}/estado', [ProveedorApiController::class, 'estado']);

        // HU-06: Registro de entradas de inventario
        Route::get('/entradas/catalogos', [EntradaInventarioApiController::class, 'catalogos']);
        Route::post('/entradas', [EntradaInventarioApiController::class, 'store']);
    });

    Route::middleware('role:ADMINISTRADOR,VENDEDOR')->group(function (): void {
        Route::post('/ventas', [VentaController::class, 'store']);
    });
});
