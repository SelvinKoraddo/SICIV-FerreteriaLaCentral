<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la historia HU-07 «Consulta de existencias».
 *
 * Se ejecutan sobre SQLite en memoria y nunca acceden a la base compartida.
 */
class ExistenciasConsultaTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $herramientas;

    private Categoria $electricos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->herramientas = Categoria::create(['nombre' => 'Herramientas', 'activa' => true]);
        $this->electricos = Categoria::create(['nombre' => 'Eléctricos', 'activa' => true]);

        // Escenario: 2 agotados, 2 bajo mínimo (uno exactamente en el mínimo), 2 normales y 1 inactivo agotado.
        $this->producto('Martillo', $this->herramientas, existencia: 0, minimo: 5);
        $this->producto('Cinta métrica', $this->herramientas, existencia: 0, minimo: 0);
        $this->producto('Cable THHN', $this->electricos, existencia: 2.5, minimo: 10);
        $this->producto('Tomacorriente', $this->electricos, existencia: 5, minimo: 5);
        $this->producto('Desarmador', $this->herramientas, existencia: 40, minimo: 10);
        $this->producto('Foco LED', $this->electricos, existencia: 12, minimo: 0);
        $this->producto('Taladro descontinuado', $this->herramientas, existencia: 0, minimo: 1, activo: false);
    }

    public function test_lista_solo_activos_ordenados_por_criticidad_con_diferencia(): void
    {
        $respuesta = $this->actingAs($this->usuario('ENCARGADO_INVENTARIO'))->getJson('/api/existencias');

        $respuesta->assertOk()->assertJsonCount(6, 'data');

        $filas = collect($respuesta->json('data'));

        // CA-3 / orden: agotados → bajo mínimo → normales; dentro del nivel, menor margen primero.
        $this->assertSame(
            ['Martillo', 'Cinta métrica', 'Cable THHN', 'Tomacorriente', 'Foco LED', 'Desarmador'],
            $filas->pluck('nombre')->all(),
        );
        $this->assertSame(
            ['agotado', 'agotado', 'bajo_minimo', 'bajo_minimo', 'normal', 'normal'],
            $filas->pluck('nivel')->all(),
        );

        // CA-2: existencia, mínimo y diferencia.
        $cable = $filas->firstWhere('nombre', 'Cable THHN');
        $this->assertEquals(2.5, $cable['existencia_actual']);
        $this->assertEquals(10, $cable['existencia_minima']);
        $this->assertEquals(-7.5, $cable['diferencia']);
        $this->assertSame('Bajo mínimo', $cable['nivel_etiqueta']);
        $this->assertSame('Eléctricos', $cable['categoria']);

        $this->assertEquals(0, $filas->firstWhere('nombre', 'Tomacorriente')['diferencia']);
        $this->assertEquals(30, $filas->firstWhere('nombre', 'Desarmador')['diferencia']);
        $this->assertNull($filas->firstWhere('nombre', 'Taladro descontinuado'), 'Los inactivos no se consultan (RN-04).');
    }

    public function test_filtra_por_nivel_de_stock(): void
    {
        $usuario = $this->usuario('VENDEDOR');

        $this->actingAs($usuario)->getJson('/api/existencias?nivel=agotado')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.*.nivel', ['agotado', 'agotado']);

        $this->actingAs($usuario)->getJson('/api/existencias?nivel=bajo_minimo')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.*.nombre', ['Cable THHN', 'Tomacorriente']);

        $this->actingAs($usuario)->getJson('/api/existencias?nivel=normal')
            ->assertOk()->assertJsonCount(2, 'data');

        // Un nivel desconocido se ignora en lugar de fallar.
        $this->actingAs($usuario)->getJson('/api/existencias?nivel=otro')
            ->assertOk()->assertJsonCount(6, 'data');
    }

    public function test_filtra_por_producto_y_por_categoria(): void
    {
        $usuario = $this->usuario('ADMINISTRADOR');

        $this->actingAs($usuario)->getJson('/api/existencias?search=mart')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Martillo');

        $this->actingAs($usuario)->getJson("/api/existencias?categoria={$this->electricos->id_categoria}")
            ->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($usuario)->getJson("/api/existencias?categoria={$this->electricos->id_categoria}&nivel=normal")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Foco LED');

        $codigo = Producto::where('nombre', 'Foco LED')->value('codigo');
        $this->actingAs($usuario)->getJson("/api/existencias?search={$codigo}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.codigo', $codigo);
    }

    public function test_resumen_cuenta_por_nivel_y_respeta_busqueda_y_categoria_pero_no_el_nivel(): void
    {
        $usuario = $this->usuario('ENCARGADO_INVENTARIO');

        $this->actingAs($usuario)->getJson('/api/existencias/resumen')
            ->assertOk()
            ->assertJson(['total' => 6, 'agotado' => 2, 'bajo_minimo' => 2, 'normal' => 2, 'en_alerta' => 4])
            ->assertJsonStructure(['consultado_en']);

        $this->actingAs($usuario)->getJson("/api/existencias/resumen?categoria={$this->herramientas->id_categoria}")
            ->assertOk()
            ->assertJson(['total' => 3, 'agotado' => 2, 'bajo_minimo' => 0, 'normal' => 1]);

        // El nivel acota el listado, pero el resumen siempre muestra el panorama completo de los filtros base.
        $this->actingAs($usuario)->getJson('/api/existencias/resumen?nivel=agotado')
            ->assertOk()
            ->assertJson(['total' => 6, 'agotado' => 2]);
    }

    public function test_resumen_sin_productos_devuelve_ceros(): void
    {
        Producto::query()->delete();

        $this->actingAs($this->usuario('VENDEDOR'))->getJson('/api/existencias/resumen')
            ->assertOk()
            ->assertJson(['total' => 0, 'agotado' => 0, 'bajo_minimo' => 0, 'normal' => 0, 'en_alerta' => 0]);
    }

    public function test_los_tres_roles_pueden_consultar_y_sin_sesion_responde_401(): void
    {
        $this->getJson('/api/existencias')->assertUnauthorized();
        $this->getJson('/api/existencias/resumen')->assertUnauthorized();

        foreach (['ADMINISTRADOR', 'ENCARGADO_INVENTARIO', 'VENDEDOR'] as $rol) {
            $this->actingAs($this->usuario($rol))->getJson('/api/existencias')->assertOk();
            $this->actingAs($this->usuario($rol))->getJson('/api/existencias/resumen')->assertOk();
        }
    }

    public function test_tamano_de_pagina_se_acota_y_la_paginacion_informa_el_total(): void
    {
        $respuesta = $this->actingAs($this->usuario('VENDEDOR'))->getJson('/api/existencias?per_page=2');

        $respuesta->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 6)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('last_page', 3);

        $this->actingAs($this->usuario('VENDEDOR'))->getJson('/api/existencias?per_page=9999')
            ->assertOk()->assertJsonPath('per_page', 500);
    }

    public function test_exportacion_del_catalogo_admite_filtro_por_nivel(): void
    {
        $contenido = $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->get('/api/productos/exportar?estado=activos&nivel=agotado')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Martillo', $contenido);
        $this->assertStringContainsString('Cinta métrica', $contenido);
        $this->assertStringNotContainsString('Desarmador', $contenido);
        $this->assertStringNotContainsString('Taladro descontinuado', $contenido);
    }

    private function producto(string $nombre, Categoria $categoria, float $existencia, float $minimo, bool $activo = true): Producto
    {
        static $correlativo = 0;

        return Producto::create([
            'codigo' => sprintf('PRD-%06d', ++$correlativo),
            'nombre' => $nombre,
            'unidad_medida' => 'UNIDAD',
            'precio_venta' => 1,
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
