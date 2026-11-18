<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_editar_un_proveedor_sin_rechazar_su_mismo_nit(): void
    {
        $admin = User::factory()->create([
            'usuario' => fake()->unique()->userName(),
            'rol' => 'ADMINISTRADOR',
            'activo' => true,
        ]);
        $proveedor = Proveedor::create([
            'nombre' => 'Proveedor original',
            'nit' => '900100004-4',
            'telefono' => '3000000000',
            'correo' => 'original@example.com',
            'direccion' => 'Dirección original',
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/proveedores/{$proveedor->getKey()}", [
                'nombre' => 'Proveedor actualizado',
                'nit' => $proveedor->nit,
                'telefono' => '3111111111',
                'correo' => 'actualizado@example.com',
                'direccion' => 'Dirección actualizada',
            ])
            ->assertOk()
            ->assertJsonPath('nombre', 'Proveedor actualizado')
            ->assertJsonPath('nit', $proveedor->nit);
    }
}
