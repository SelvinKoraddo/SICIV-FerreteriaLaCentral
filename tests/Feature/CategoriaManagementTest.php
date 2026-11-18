<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_editar_y_cambiar_estado_de_una_categoria(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');

        $creada = $this->actingAs($admin)
            ->postJson('/api/categorias', ['nombre' => 'Herramientas', 'descripcion' => 'Manuales'])
            ->assertCreated()
            ->assertJsonPath('categoria.nombre', 'Herramientas')
            ->assertJsonPath('categoria.activa', true)
            ->json('categoria');

        $this->assertSame(sprintf('CAT-%06d', $creada['id_categoria']), $creada['codigo']);

        $this->actingAs($admin)
            ->putJson("/api/categorias/{$creada['id_categoria']}", [
                'nombre' => 'Herramientas manuales',
                'descripcion' => 'Herramientas de uso manual',
            ])
            ->assertOk()
            ->assertJsonPath('categoria.nombre', 'Herramientas manuales')
            ->assertJsonPath('categoria.descripcion', 'Herramientas de uso manual');

        $this->actingAs($admin)
            ->patchJson("/api/categorias/{$creada['id_categoria']}/estado", ['activa' => false])
            ->assertOk()
            ->assertJsonPath('categoria.activa', false);

        $this->actingAs($admin)
            ->patchJson("/api/categorias/{$creada['id_categoria']}/estado", ['activa' => true])
            ->assertOk()
            ->assertJsonPath('categoria.activa', true);
    }

    public function test_vendedor_no_puede_administrar_categorias(): void
    {
        $categoria = Categoria::create(['nombre' => 'Herramientas', 'activa' => true]);
        $vendedor = $this->usuario('VENDEDOR');

        $this->actingAs($vendedor)
            ->postJson('/api/categorias', ['nombre' => 'Eléctricos'])
            ->assertForbidden();

        $this->actingAs($vendedor)
            ->putJson("/api/categorias/{$categoria->id_categoria}", ['nombre' => 'Eléctricos'])
            ->assertForbidden();

        $this->actingAs($vendedor)
            ->patchJson("/api/categorias/{$categoria->id_categoria}/estado", ['activa' => false])
            ->assertForbidden();

        $this->assertDatabaseHas('categorias', ['id_categoria' => $categoria->id_categoria, 'activa' => true]);
    }

    public function test_no_permite_desactivar_categoria_con_productos_activos(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $categoria = Categoria::create(['nombre' => 'Herramientas', 'activa' => true]);
        Producto::create([
            'codigo' => 'PRD-000001',
            'nombre' => 'Martillo',
            'unidad_medida' => 'UNIDAD',
            'precio_venta' => 8.50,
            'existencia_actual' => 0,
            'existencia_minima' => 0,
            'activo' => true,
            'id_categoria' => $categoria->id_categoria,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/categorias/{$categoria->id_categoria}/estado", ['activa' => false])
            ->assertForbidden();

        $this->assertDatabaseHas('categorias', ['id_categoria' => $categoria->id_categoria, 'activa' => true]);
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