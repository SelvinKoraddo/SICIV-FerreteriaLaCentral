<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Pruebas de la historia HU-03 «Registro de productos».
 *
 * Se ejecutan sobre SQLite en memoria (ver phpunit.xml) y nunca acceden a la
 * base de datos compartida del equipo.
 */
class ProductoRegistroTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = Categoria::create([
            'nombre' => 'Herramientas',
            'descripcion' => 'Herramientas manuales',
            'activa' => true,
        ]);
    }

    // ------------------------------------------------------------------
    //  Alta de productos
    // ------------------------------------------------------------------

    public function test_administrador_registra_producto_y_recibe_codigo_generado(): void
    {
        $respuesta = $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->postJson('/api/productos', $this->datosProducto());

        $respuesta->assertCreated()
            ->assertJsonPath('producto.nombre', 'Martillo de uña 16 oz')
            ->assertJsonPath('producto.categoria.nombre', 'Herramientas');

        $codigo = $respuesta->json('producto.codigo');

        $this->assertMatchesRegularExpression('/^PRD-\d{6}$/', $codigo);                 // CA-2
        $this->assertStringContainsString($codigo, $respuesta->json('message'));          // CA-4
        $this->assertDatabaseHas('productos', ['codigo' => $codigo, 'existencia_actual' => 10, 'activo' => 1]);
    }

    public function test_codigos_generados_son_unicos_y_derivados_del_id(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');

        $primero = $this->registrar($admin, ['nombre' => 'Producto A']);
        $segundo = $this->registrar($admin, ['nombre' => 'Producto B']);

        $this->assertNotSame($primero['codigo'], $segundo['codigo']);
        $this->assertSame(sprintf('PRD-%06d', $primero['id_producto']), $primero['codigo']);
        $this->assertSame(sprintf('PRD-%06d', $segundo['id_producto']), $segundo['codigo']);
        $this->assertSame(0, Producto::where('codigo', 'like', 'TMP-%')->count(), 'El código temporal no debe persistir.');
    }

    public function test_existencia_inicial_mayor_que_cero_genera_movimiento_de_apertura(): void
    {
        $producto = $this->registrar($this->usuario('ADMINISTRADOR'), ['existencia_inicial' => 25]);

        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseHas('movimientos_inventario', [
            'id_producto' => $producto['id_producto'],
            'tipo' => 'APERTURA',
            'cantidad' => 25,
            'saldo_anterior' => 0,
            'saldo_resultante' => 25,
            'referencia' => 'Apertura '.$producto['codigo'],
        ]);
    }

    public function test_existencia_inicial_cero_u_omitida_no_genera_movimiento(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');

        $this->registrar($admin, ['existencia_inicial' => 0]);
        $producto = $this->registrar($admin, ['existencia_inicial' => null]);

        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertEquals(0, (float) $producto['existencia_actual']);
    }

    public function test_cliente_no_puede_imponer_codigo_ni_existencia_actual(): void
    {
        $producto = $this->registrar($this->usuario('ADMINISTRADOR'), [
            'codigo' => 'MANUAL-001',
            'existencia_actual' => 9999,
            'existencia_inicial' => 4,
        ]);

        $this->assertNotSame('MANUAL-001', $producto['codigo']);
        $this->assertEquals(4, (float) $producto['existencia_actual']);
    }

    public function test_conflicto_con_codigo_registrado_manualmente_responde_409_sin_guardar(): void
    {
        // Registro previo a HU-03 que ocupa el código que corresponderá al siguiente id.
        Producto::create($this->datosModelo(['codigo' => 'PRD-000002']));

        $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->postJson('/api/productos', $this->datosProducto())
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $mensaje) => str_contains($mensaje, 'PRD-000002'));

        $this->assertDatabaseCount('productos', 1);
    }

    public function test_si_falla_el_movimiento_de_apertura_se_revierte_el_alta(): void
    {
        MovimientoInventario::creating(static fn () => throw new RuntimeException('Fallo simulado'));

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->usuario('ADMINISTRADOR'))
                ->postJson('/api/productos', $this->datosProducto(['existencia_inicial' => 7]));
            $this->fail('Se esperaba que el alta fallara.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado', $exception->getMessage());
        }

        $this->assertDatabaseCount('productos', 0);                                       // CU-03 8a
    }

    // ------------------------------------------------------------------
    //  Validaciones
    // ------------------------------------------------------------------

    public function test_rechaza_categoria_inactiva(): void
    {
        $inactiva = Categoria::create(['nombre' => 'Descontinuados', 'activa' => false]);

        $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->postJson('/api/productos', $this->datosProducto(['id_categoria' => $inactiva->id_categoria]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_categoria']);

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_rechaza_negativos_precio_cero_y_unidad_invalida(): void
    {
        $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->postJson('/api/productos', $this->datosProducto([
                'precio_venta' => 0,
                'existencia_inicial' => -1,
                'existencia_minima' => -5,
                'unidad_medida' => 'TONELADA',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio_venta', 'existencia_inicial', 'existencia_minima', 'unidad_medida']);

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_rechaza_mas_de_dos_decimales_para_evitar_redondeo_en_mysql(): void
    {
        $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->postJson('/api/productos', $this->datosProducto([
                'precio_venta' => 0.001,
                'unidad_medida' => 'METRO',
                'existencia_inicial' => 2.555,
                'existencia_minima' => 1.125,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio_venta', 'existencia_inicial', 'existencia_minima']);

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_fracciones_segun_la_unidad_de_medida(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');

        $this->actingAs($admin)
            ->postJson('/api/productos', $this->datosProducto(['unidad_medida' => 'CAJA', 'existencia_inicial' => 2.5]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['existencia_inicial']);

        $this->registrar($admin, [
            'nombre' => 'Cable THHN #12',
            'unidad_medida' => 'METRO',
            'existencia_inicial' => 2.5,
            'existencia_minima' => 0.5,
        ]);
    }

    // ------------------------------------------------------------------
    //  Autorización
    // ------------------------------------------------------------------

    public function test_vendedor_no_puede_registrar_productos(): void
    {
        $this->actingAs($this->usuario('VENDEDOR'))
            ->postJson('/api/productos', $this->datosProducto())
            ->assertForbidden();

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_encargado_de_inventario_puede_registrar_productos(): void
    {
        $this->actingAs($this->usuario('ENCARGADO_INVENTARIO'))
            ->postJson('/api/productos', $this->datosProducto())
            ->assertCreated();
    }

    public function test_sin_sesion_responde_no_autenticado(): void
    {
        $this->postJson('/api/productos', $this->datosProducto())->assertUnauthorized();
    }

    // ------------------------------------------------------------------
    //  Edición de productos
    // ------------------------------------------------------------------

    public function test_editar_actualiza_datos_sin_tocar_codigo_ni_existencia(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $creado = $this->registrar($admin, ['existencia_inicial' => 12]);
        $otraCategoria = Categoria::create(['nombre' => 'Eléctricos', 'activa' => true]);

        $respuesta = $this->actingAs($admin)->putJson("/api/productos/{$creado['id_producto']}", [
            ...$this->datosEdicion([
                'nombre' => 'Martillo de uña 20 oz',
                'id_categoria' => $otraCategoria->id_categoria,
                'precio_venta' => 11.25,
                'existencia_minima' => 5,
            ]),
            // Campos que deben ignorarse.
            'codigo' => 'OTRO-999',
            'existencia_actual' => 500,
            'existencia_inicial' => 500,
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('producto.nombre', 'Martillo de uña 20 oz')
            ->assertJsonPath('producto.categoria.nombre', 'Eléctricos')
            ->assertJsonPath('producto.codigo', $creado['codigo']);

        $this->assertDatabaseHas('productos', [
            'id_producto' => $creado['id_producto'],
            'codigo' => $creado['codigo'],
            'existencia_actual' => 12,
            'existencia_minima' => 5,
        ]);
        $this->assertSame(1, MovimientoInventario::count(), 'Editar no debe generar movimientos (RN-02).');
    }

    public function test_vendedor_no_puede_editar_y_producto_inexistente_responde_404(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $creado = $this->registrar($admin);

        $this->actingAs($this->usuario('VENDEDOR'))
            ->putJson("/api/productos/{$creado['id_producto']}", $this->datosEdicion(['nombre' => 'Cambiado']))
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson('/api/productos/999999', $this->datosEdicion())
            ->assertNotFound();

        $this->assertDatabaseHas('productos', ['id_producto' => $creado['id_producto'], 'nombre' => 'Martillo de uña 16 oz']);
    }

    public function test_editar_conserva_categoria_inactiva_pero_no_reasigna_a_otra_inactiva(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $creado = $this->registrar($admin);
        $this->categoria->update(['activa' => false]);
        $otraInactiva = Categoria::create(['nombre' => 'Obsoletos', 'activa' => false]);

        $this->actingAs($admin)
            ->putJson("/api/productos/{$creado['id_producto']}", $this->datosEdicion(['nombre' => 'Martillo renombrado']))
            ->assertOk()
            ->assertJsonPath('producto.nombre', 'Martillo renombrado');

        $this->actingAs($admin)
            ->putJson("/api/productos/{$creado['id_producto']}", $this->datosEdicion(['id_categoria' => $otraInactiva->id_categoria]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_categoria']);                              // RN-03
    }

    public function test_no_cambia_a_unidad_sin_fracciones_si_la_existencia_tiene_decimales(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $creado = $this->registrar($admin, [
            'nombre' => 'Cable',
            'unidad_medida' => 'METRO',
            'existencia_inicial' => 2.5,
            'existencia_minima' => 1,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/productos/{$creado['id_producto']}", $this->datosEdicion(['unidad_medida' => 'CAJA']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unidad_medida']);

        $this->actingAs($admin)
            ->putJson("/api/productos/{$creado['id_producto']}", $this->datosEdicion(['unidad_medida' => 'YARDA']))
            ->assertOk()
            ->assertJsonPath('producto.unidad_medida', 'YARDA');
    }

    // ------------------------------------------------------------------
    //  Activación y desactivación (RN-08)
    // ------------------------------------------------------------------

    public function test_desactivar_y_reactivar_conserva_el_producto_y_su_historial(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $creado = $this->registrar($admin, ['existencia_inicial' => 5]);

        $this->actingAs($admin)
            ->patchJson("/api/productos/{$creado['id_producto']}/estado", ['activo' => false])
            ->assertOk()
            ->assertJsonPath('producto.activo', false)
            ->assertJsonPath('message', "Producto {$creado['codigo']} desactivado.");

        $this->assertDatabaseHas('productos', ['id_producto' => $creado['id_producto'], 'activo' => 0, 'codigo' => $creado['codigo']]);
        $this->assertSame(1, MovimientoInventario::count(), 'Desactivar no elimina el historial.');

        $this->actingAs($admin)
            ->patchJson("/api/productos/{$creado['id_producto']}/estado", ['activo' => true])
            ->assertOk()
            ->assertJsonPath('producto.activo', true)
            ->assertJsonPath('message', "Producto {$creado['codigo']} activado.");
    }

    public function test_cambio_de_estado_valida_el_dato_y_los_permisos(): void
    {
        $creado = $this->registrar($this->usuario('ADMINISTRADOR'));

        $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->patchJson("/api/productos/{$creado['id_producto']}/estado", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['activo']);

        $this->actingAs($this->usuario('VENDEDOR'))
            ->patchJson("/api/productos/{$creado['id_producto']}/estado", ['activo' => false])
            ->assertForbidden();

        $this->assertDatabaseHas('productos', ['id_producto' => $creado['id_producto'], 'activo' => 1]);
    }

    public function test_filtro_por_estado_en_el_listado(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $activo = $this->registrar($admin, ['nombre' => 'Producto activo']);
        $inactivo = $this->registrar($admin, ['nombre' => 'Producto inactivo']);
        $this->actingAs($admin)->patchJson("/api/productos/{$inactivo['id_producto']}/estado", ['activo' => false]);

        $this->actingAs($admin)->getJson('/api/productos?estado=activos')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_producto', $activo['id_producto']);

        $this->actingAs($admin)->getJson('/api/productos?estado=inactivos')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_producto', $inactivo['id_producto']);

        $this->actingAs($admin)->getJson('/api/productos')
            ->assertOk()->assertJsonCount(2, 'data');

        // Compatibilidad con las vistas existentes que envían activos=true.
        $this->actingAs($admin)->getJson('/api/productos?activos=1')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    // ------------------------------------------------------------------
    //  Exportación del catálogo
    // ------------------------------------------------------------------

    public function test_exporta_el_catalogo_en_csv_compatible_con_excel(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $producto = $this->registrar($admin, ['nombre' => 'Llave inglesa 10"', 'existencia_inicial' => 2, 'existencia_minima' => 5]);

        $respuesta = $this->actingAs($admin)->get('/api/productos/exportar');

        $respuesta->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertMatchesRegularExpression('/attachment; filename=catalogo_productos_\d{8}_\d{4}\.csv/', $respuesta->headers->get('content-disposition'));

        $contenido = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido, 'El BOM permite que Excel muestre las tildes.');

        $filas = array_map(fn (string $linea) => str_getcsv($linea, ',', '"', ''), preg_split('/\R/', trim(substr($contenido, 3))));
        $this->assertSame('Código', $filas[0][0]);
        $this->assertCount(2, $filas);
        $this->assertSame([
            $producto['codigo'], 'Llave inglesa 10"', 'Mango de fibra de vidrio', 'Herramientas',
            'UNIDAD', '8.50', '2.00', '5.00', 'Bajo mínimo', 'Activo',
        ], $filas[1]);
    }

    public function test_exportacion_respeta_filtros_y_neutraliza_formulas(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $this->registrar($admin, ['nombre' => '=HYPERLINK("http://ejemplo.test")']);
        $inactivo = $this->registrar($admin, ['nombre' => 'Producto descontinuado']);
        $this->actingAs($admin)->patchJson("/api/productos/{$inactivo['id_producto']}/estado", ['activo' => false]);

        $contenido = $this->actingAs($admin)->get('/api/productos/exportar?estado=activos')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $contenido, 'Las fórmulas deben quedar como texto.');
        $this->assertStringNotContainsString('Producto descontinuado', $contenido);
    }

    public function test_vendedor_no_puede_exportar_el_catalogo(): void
    {
        $this->actingAs($this->usuario('VENDEDOR'))
            ->get('/api/productos/exportar')
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    //  Catálogos auxiliares
    // ------------------------------------------------------------------

    public function test_unidades_de_medida_disponibles_para_el_formulario(): void
    {
        $this->actingAs($this->usuario('VENDEDOR'))
            ->getJson('/api/productos/unidades-medida')
            ->assertOk()
            ->assertJsonCount(15)
            ->assertJsonFragment(['valor' => 'UNIDAD', 'etiqueta' => 'Unidad', 'permite_fracciones' => false])
            ->assertJsonFragment(['valor' => 'METRO', 'etiqueta' => 'Metro', 'permite_fracciones' => true]);
    }

    // ------------------------------------------------------------------
    //  Utilidades
    // ------------------------------------------------------------------

    /**
     * Registra un producto por la API y devuelve su representación JSON.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function registrar(User $usuario, array $cambios = []): array
    {
        return $this->actingAs($usuario)
            ->postJson('/api/productos', $this->datosProducto($cambios))
            ->assertCreated()
            ->json('producto');
    }

    /**
     * Cuerpo válido para el alta de un producto.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosProducto(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Martillo de uña 16 oz',
            'descripcion' => 'Mango de fibra de vidrio',
            'id_categoria' => $this->categoria->id_categoria,
            'unidad_medida' => 'UNIDAD',
            'precio_venta' => 8.50,
            'existencia_inicial' => 10,
            'existencia_minima' => 3,
        ], $cambios);
    }

    /**
     * Cuerpo válido para la edición (sin existencia inicial).
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosEdicion(array $cambios = []): array
    {
        $datos = $this->datosProducto($cambios);
        unset($datos['existencia_inicial']);

        return $datos;
    }

    /**
     * Atributos para crear un producto directamente con el modelo.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosModelo(array $cambios = []): array
    {
        $datos = $this->datosProducto();
        unset($datos['existencia_inicial']);

        return array_merge($datos, ['codigo' => 'LEGACY-001', 'existencia_actual' => 0, 'activo' => true], $cambios);
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
