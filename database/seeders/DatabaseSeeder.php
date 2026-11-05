<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\DetalleEntrada;
use App\Models\DetalleVenta;
use App\Models\EntradaInventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Venta;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //TODOS LOS DATOS INSERTADOS SON DE PRUEBA, NO SON REALES NI SE USAN EN PRODUCCIÓN
        $usuarios = collect([
            
            ['name' => 'Administrador', 'email' => 'admin@ferreteria.test', 'usuario' => 'admin', 'rol' => 'ADMINISTRADOR'],
            ['name' => 'Carlos Vendedor', 'email' => 'carlos@ferreteria.test', 'usuario' => 'carlos', 'rol' => 'VENDEDOR'],
            ['name' => 'Ana Vendedora', 'email' => 'ana@ferreteria.test', 'usuario' => 'ana', 'rol' => 'VENDEDOR'],
            ['name' => 'Luis Inventario', 'email' => 'luis@ferreteria.test', 'usuario' => 'luis', 'rol' => 'ENCARGADO_INVENTARIO'],
            ['name' => 'María Vendedora', 'email' => 'maria@ferreteria.test', 'usuario' => 'maria', 'rol' => 'VENDEDOR'],
        ])->map(fn (array $datos): User => User::create($datos + ['password' => 'password']));

        $categorias = collect([
            ['nombre' => 'Herramientas', 'descripcion' => 'Herramientas manuales y eléctricas'],
            ['nombre' => 'Tornillería', 'descripcion' => 'Tornillos, tuercas y fijaciones'],
            ['nombre' => 'Plomería', 'descripcion' => 'Accesorios para instalaciones de agua'],
            ['nombre' => 'Electricidad', 'descripcion' => 'Materiales y accesorios eléctricos'],
            ['nombre' => 'Pintura', 'descripcion' => 'Pinturas, brochas y complementos'],
        ])->map(fn (array $datos): Categoria => Categoria::create($datos));

        $proveedores = collect([
            ['nombre' => 'Herramientas del Norte', 'nit' => '900100001-1', 'telefono' => '5551001', 'correo' => 'ventas@norte.test', 'direccion' => 'Av. Principal 101'],
            ['nombre' => 'Suministros El Tornillo', 'nit' => '900100002-2', 'telefono' => '5551002', 'correo' => 'contacto@tornillo.test', 'direccion' => 'Calle 10 202'],
            ['nombre' => 'Plásticos y Tuberías', 'nit' => '900100003-3', 'telefono' => '5551003', 'correo' => 'ventas@plasticos.test', 'direccion' => 'Carrera 5 303'],
            ['nombre' => 'Electrocentro', 'nit' => '900100004-4', 'telefono' => '5551004', 'correo' => 'info@electrocentro.test', 'direccion' => 'Calle 20 404'],
            ['nombre' => 'Pinturas La Central', 'nit' => '900100005-5', 'telefono' => '5551005', 'correo' => 'pedidos@pinturas.test', 'direccion' => 'Carrera 8 505'],
        ])->map(fn (array $datos): Proveedor => Proveedor::create($datos));

        $productos = collect([
            ['codigo' => 'TAL-001', 'nombre' => 'Taladro eléctrico', 'unidad_medida' => 'UNIDAD', 'precio_venta' => 480, 'existencia_minima' => 5],
            ['codigo' => 'TOR-001', 'nombre' => 'Tornillo hexagonal 1/4', 'unidad_medida' => 'CAJA', 'precio_venta' => 85, 'existencia_minima' => 10],
            ['codigo' => 'PVC-001', 'nombre' => 'Tubo PVC 1/2 pulgada', 'unidad_medida' => 'METRO', 'precio_venta' => 24, 'existencia_minima' => 20],
            ['codigo' => 'CAB-001', 'nombre' => 'Cable eléctrico calibre 12', 'unidad_medida' => 'METRO', 'precio_venta' => 18, 'existencia_minima' => 30],
            ['codigo' => 'PIN-001', 'nombre' => 'Pintura blanca 1 galón', 'unidad_medida' => 'GALON', 'precio_venta' => 210, 'existencia_minima' => 5],
        ])->values()->map(function (array $datos, int $indice) use ($categorias): Producto {
            return Producto::create($datos + [
                'descripcion' => 'Producto de ejemplo para pruebas',
                'existencia_actual' => 0,
                'id_categoria' => $categorias[$indice]->id_categoria,
            ]);
        });

        $productos->each(function (Producto $producto, int $indice) use ($proveedores, $usuarios): void {
            $cantidad = 50;
            $costo = round((float) $producto->precio_venta * 0.6, 2);
            $entrada = EntradaInventario::create([
                'numero' => sprintf('ENT-%04d', $indice + 1),
                'fecha' => now()->subDays(10 - $indice),
                'referencia' => 'Compra inicial',
                'total' => $cantidad * $costo,
                'id_proveedor' => $proveedores[$indice]->id_proveedor,
                'id_usuario' => $usuarios[3]->id,
            ]);
            $detalle = DetalleEntrada::create([
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'subtotal' => $cantidad * $costo,
                'id_entrada' => $entrada->id_entrada,
                'id_producto' => $producto->id_producto,
            ]);
            $producto->update(['existencia_actual' => $cantidad]);
            MovimientoInventario::create([
                'fecha' => $entrada->fecha,
                'tipo' => 'RECEPCION',
                'cantidad' => $cantidad,
                'saldo_anterior' => 0,
                'saldo_resultante' => $cantidad,
                'referencia' => $entrada->numero,
                'id_producto' => $producto->id_producto,
                'id_detalle_entrada' => $detalle->id_detalle_entrada,
            ]);
        });

        $productos->each(function (Producto $producto, int $indice) use ($usuarios): void {
            $cantidad = 2;
            $subtotal = $cantidad * (float) $producto->precio_venta;
            $venta = Venta::create([
                'numero' => sprintf('VTA-%04d', $indice + 1),
                'fecha' => now()->subDays(4 - $indice),
                'total' => $subtotal,
                'id_usuario' => $usuarios[$indice % $usuarios->count()]->id,
            ]);
            $detalle = DetalleVenta::create([
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio_venta,
                'subtotal' => $subtotal,
                'id_venta' => $venta->id_venta,
                'id_producto' => $producto->id_producto,
            ]);
            $saldoAnterior = (float) $producto->existencia_actual;
            $producto->update(['existencia_actual' => $saldoAnterior - $cantidad]);
            MovimientoInventario::create([
                'fecha' => $venta->fecha,
                'tipo' => 'VENTA',
                'cantidad' => $cantidad,
                'saldo_anterior' => $saldoAnterior,
                'saldo_resultante' => $saldoAnterior - $cantidad,
                'referencia' => $venta->numero,
                'id_producto' => $producto->id_producto,
                'id_detalle_venta' => $detalle->id_detalle_venta,
            ]);
        });
    }
}
