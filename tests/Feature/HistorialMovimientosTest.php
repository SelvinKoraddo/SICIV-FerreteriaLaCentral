<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialMovimientosTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private Producto $producto;

    // Estas pruebas solo pueden crear datos en una base temporal en memoria.
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || filled(config('database.connections.sqlite.url'))) {
            throw new \LogicException('Las pruebas del Kardex requieren SQLite en memoria.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendedor = User::factory()->create(['usuario' => 'vendedor-propio', 'rol' => 'VENDEDOR', 'activo' => true]);
        $otro = User::factory()->create(['usuario' => 'vendedor-ajeno', 'rol' => 'VENDEDOR', 'activo' => true]);
        $categoria = Categoria::create(['nombre' => 'Herramientas', 'activa' => true]);
        $this->producto = Producto::create([
            'codigo' => 'PRD-000001', 'nombre' => 'Martillo', 'unidad_medida' => 'UNIDAD',
            'precio_venta' => 5, 'existencia_actual' => 20, 'existencia_minima' => 1,
            'activo' => true, 'id_categoria' => $categoria->id_categoria,
        ]);

        foreach (['APERTURA', 'RECEPCION'] as $tipo) {
            $this->movimiento($tipo);
        }
        foreach ([$this->vendedor, $otro, $this->vendedor] as $index => $usuario) {
            $venta = Venta::create([
                'numero' => 'VTA-'.($index + 1), 'fecha' => '2026-11-06 12:00:00',
                'total' => 5, 'estado' => 'CONFIRMADA', 'id_usuario' => $usuario->id,
            ]);
            $detalle = $venta->detalles()->create([
                'id_producto' => $this->producto->id_producto,
                'cantidad' => 1, 'precio_unitario' => 5, 'subtotal' => 5,
            ]);
            $this->movimiento('VENTA', ['id_detalle_venta' => $detalle->id_detalle_venta]);
        }
    }

    public function test_vendedor_solo_ve_sus_ventas_y_el_conteo_tambien_se_limita(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/movimientos?per_page=1')
            ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('last_page', 2)
            ->assertJsonPath('data.0.id_movimiento', 3)->assertJsonMissingPath('data.0.usuario');

        $this->getJson('/api/movimientos?per_page=1&page=2')
            ->assertOk()->assertJsonPath('data.0.id_movimiento', 5);
    }

    public function test_los_filtros_no_permiten_ver_movimientos_ajenos(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/movimientos?tipo=RECEPCION')
            ->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/movimientos?tipo=APERTURA')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/movimientos?usuario=2&producto='.$this->producto->id_producto)
            ->assertOk()->assertJsonPath('total', 2);
    }

    public function test_administrador_y_encargado_ven_todos_sin_columna_de_usuario(): void
    {
        foreach (['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'] as $rol) {
            $usuario = User::factory()->create(['usuario' => $rol, 'rol' => $rol, 'activo' => true]);
            $this->actingAs($usuario)->getJson('/api/movimientos')
                ->assertOk()->assertJsonPath('total', 5)->assertJsonCount(5, 'data')
                ->assertJsonMissingPath('data.0.usuario');
        }
    }

    public function test_sin_sesion_no_se_puede_consultar_el_historial(): void
    {
        $this->getJson('/api/movimientos')->assertUnauthorized();
    }

    private function movimiento(string $tipo, array $extra = []): void
    {
        MovimientoInventario::create([
            'id_producto' => $this->producto->id_producto,
            'fecha' => '2026-11-06 12:00:00', 'tipo' => $tipo,
            'cantidad' => 1, 'saldo_anterior' => 20, 'saldo_resultante' => 19,
            'referencia' => 'Prueba de permisos', ...$extra,
        ]);
    }
}
