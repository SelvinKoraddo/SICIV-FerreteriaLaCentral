<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la historia HU-08 «Alertas de existencia baja y agotados».
 *
 * Se ejecutan sobre SQLite en memoria y nunca acceden a la base compartida.
 */
class AlertasTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $herramientas;

    private Categoria $electricos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->herramientas = Categoria::create(['nombre' => 'Herramientas', 'activa' => true]);
        $this->electricos = Categoria::create(['nombre' => 'Eléctricos', 'activa' => true]);
    }

    public function test_lista_solo_activos_en_alerta_con_agotados_primero(): void
    {
        $this->producto('Normal', $this->herramientas, existencia: 20, minimo: 5);
        $this->producto('Bajo mínimo', $this->herramientas, existencia: 3, minimo: 5);
        $this->producto('En el mínimo', $this->electricos, existencia: 5, minimo: 5);
        $this->producto('Agotado', $this->electricos, existencia: 0, minimo: 2);
        $this->producto('Agotado sin mínimo', $this->electricos, existencia: 0, minimo: 0);
        $this->producto('Inactivo agotado', $this->herramientas, existencia: 0, minimo: 2, activo: false);

        $respuesta = $this->actingAs($this->usuario('ENCARGADO_INVENTARIO'))->getJson('/api/alertas');

        $respuesta->assertOk()->assertJsonCount(4, 'data');
        $filas = collect($respuesta->json('data'));

        // CA-2: los agotados se identifican y aparecen primero.
        $this->assertSame(['agotado', 'agotado', 'bajo_minimo', 'bajo_minimo'], $filas->pluck('nivel')->all());
        $this->assertSame(['Agotado', 'Agotado sin mínimo'], $filas->take(2)->pluck('nombre')->all());
        $this->assertSame('Agotado', $filas->first()['nivel_etiqueta']);

        // CU-08 paso 4: código, nombre, categoría, existencia actual y mínima.
        $bajo = $filas->firstWhere('nombre', 'Bajo mínimo');
        $this->assertSame('Herramientas', $bajo['categoria']);
        $this->assertEquals(3, $bajo['existencia_actual']);
        $this->assertEquals(5, $bajo['existencia_minima']);
        $this->assertEquals(2, $bajo['faltante']);
        $this->assertNotEmpty($bajo['codigo']);

        $this->assertNull($filas->firstWhere('nombre', 'Normal'));
        $this->assertNull($filas->firstWhere('nombre', 'Inactivo agotado'), 'Las alertas solo aplican a productos activos (RN-04).');
    }

    public function test_conteo_de_alertas_para_el_aviso_al_iniciar_sesion(): void
    {
        $this->producto('Agotado', $this->herramientas, existencia: 0, minimo: 1);
        $this->producto('Bajo 1', $this->herramientas, existencia: 1, minimo: 4);
        $this->producto('Bajo 2', $this->electricos, existencia: 2.5, minimo: 2.5);
        $this->producto('Normal', $this->electricos, existencia: 9, minimo: 2);

        $this->actingAs($this->usuario('VENDEDOR'))->getJson('/api/alertas/conteo')
            ->assertOk()
            ->assertExactJson(['total' => 3, 'agotado' => 1, 'bajo_minimo' => 2]);
    }

    public function test_sin_alertas_el_listado_esta_vacio_y_el_conteo_en_cero(): void
    {
        $this->producto('Normal', $this->herramientas, existencia: 10, minimo: 2);

        $usuario = $this->usuario('ADMINISTRADOR');

        $this->actingAs($usuario)->getJson('/api/alertas')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($usuario)->getJson('/api/alertas/conteo')
            ->assertOk()->assertExactJson(['total' => 0, 'agotado' => 0, 'bajo_minimo' => 0]);
    }

    public function test_filtra_por_categoria_busqueda_y_nivel(): void
    {
        $this->producto('Martillo', $this->herramientas, existencia: 0, minimo: 2);
        $this->producto('Sierra', $this->herramientas, existencia: 1, minimo: 2);
        $this->producto('Cable', $this->electricos, existencia: 0, minimo: 2);

        $usuario = $this->usuario('ENCARGADO_INVENTARIO');

        $this->actingAs($usuario)->getJson("/api/alertas?categoria={$this->herramientas->id_categoria}")
            ->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($usuario)->getJson('/api/alertas?search=cab')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Cable');

        $this->actingAs($usuario)->getJson('/api/alertas?nivel=agotado')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.*.nivel', ['agotado', 'agotado']);

        $this->actingAs($usuario)->getJson('/api/alertas?nivel=bajo_minimo')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Sierra');

        // "normal" no es un nivel de alerta: se ignora en lugar de devolver productos sin alerta.
        $this->actingAs($usuario)->getJson('/api/alertas?nivel=normal')
            ->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($usuario)->getJson("/api/alertas/conteo?categoria={$this->electricos->id_categoria}")
            ->assertOk()->assertExactJson(['total' => 1, 'agotado' => 1, 'bajo_minimo' => 0]);
    }

    public function test_una_venta_que_deja_la_existencia_en_el_minimo_activa_la_alerta(): void
    {
        $producto = $this->producto('Tornillo', $this->herramientas, existencia: 10, minimo: 4);
        $vendedor = $this->usuario('VENDEDOR');

        $this->actingAs($vendedor)->getJson('/api/alertas/conteo')->assertJsonPath('total', 0);

        // CA-1: la venta real (VentaService) descuenta 6 unidades y deja 4 = mínimo.
        $this->actingAs($vendedor)->postJson('/api/ventas', [
            'fecha' => now()->toDateTimeString(),
            'detalles' => [['id_producto' => $producto->id_producto, 'cantidad' => 6]],
        ])->assertCreated();

        $this->actingAs($vendedor)->getJson('/api/alertas/conteo')
            ->assertExactJson(['total' => 1, 'agotado' => 0, 'bajo_minimo' => 1]);
        $this->actingAs($vendedor)->getJson('/api/alertas')
            ->assertJsonPath('data.0.id_producto', $producto->id_producto)
            ->assertJsonPath('data.0.nivel', 'bajo_minimo');
    }

    public function test_una_entrada_que_supera_el_minimo_cierra_la_alerta(): void
    {
        $producto = $this->producto('Brocha', $this->herramientas, existencia: 1, minimo: 5);
        $usuario = $this->usuario('ENCARGADO_INVENTARIO');

        $this->actingAs($usuario)->getJson('/api/alertas/conteo')->assertJsonPath('total', 1);

        // CA-4: una recepción aumenta el saldo por encima del mínimo (efecto de HU-06 sobre el producto).
        $producto->update(['existencia_actual' => 12]);

        $this->actingAs($usuario)->getJson('/api/alertas/conteo')->assertJsonPath('total', 0);
        $this->actingAs($usuario)->getJson('/api/alertas')->assertJsonCount(0, 'data');
    }

    public function test_los_tres_roles_consultan_alertas_y_sin_sesion_responde_401(): void
    {
        $this->getJson('/api/alertas')->assertUnauthorized();
        $this->getJson('/api/alertas/conteo')->assertUnauthorized();

        foreach (['ADMINISTRADOR', 'ENCARGADO_INVENTARIO', 'VENDEDOR'] as $rol) {
            $this->actingAs($this->usuario($rol))->getJson('/api/alertas')->assertOk();
            $this->actingAs($this->usuario($rol))->getJson('/api/alertas/conteo')->assertOk();
        }
    }

    public function test_tamano_de_pagina_se_acota(): void
    {
        foreach (range(1, 3) as $indice) {
            $this->producto("Agotado {$indice}", $this->herramientas, existencia: 0, minimo: 1);
        }

        $usuario = $this->usuario('VENDEDOR');

        $this->actingAs($usuario)->getJson('/api/alertas?per_page=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('total', 3);

        $this->actingAs($usuario)->getJson('/api/alertas?per_page=0')
            ->assertOk()->assertJsonPath('per_page', 1);
    }

    private function producto(string $nombre, Categoria $categoria, float $existencia, float $minimo, bool $activo = true): Producto
    {
        static $correlativo = 0;

        return Producto::create([
            'codigo' => sprintf('PRD-%06d', ++$correlativo),
            'nombre' => $nombre,
            'unidad_medida' => 'UNIDAD',
            'precio_venta' => 2.5,
            'existencia_actual' => $existencia,
            'existencia_minima' => $minimo,
            'activo' => $activo,
            'id_categoria' => $categoria->id_categoria,
        ]);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create([
            'usuario' => fake()->unique()->userName(),
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
