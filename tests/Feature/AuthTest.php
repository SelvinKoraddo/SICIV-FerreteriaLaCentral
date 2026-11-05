<?php

namespace Tests\Feature;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Pruebas del inicio y cierre de sesión (HU-01).
 *
 * Se ejecutan sobre SQLite en memoria; no acceden a la base compartida.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear($this->throttleKey('vendedor'));
    }

    public function test_usuario_activo_inicia_sesion_y_recibe_solo_datos_publicos(): void
    {
        $usuario = $this->crearUsuario('vendedor', 'VENDEDOR');

        $respuesta = $this->postJson('/api/login', ['usuario' => 'vendedor', 'password' => 'secreto123']);

        $respuesta->assertOk()
            ->assertJsonPath('message', 'Sesión iniciada correctamente.')
            ->assertExactJson([
                'message' => 'Sesión iniciada correctamente.',
                'user' => ['id' => $usuario->id, 'name' => $usuario->name, 'usuario' => 'vendedor', 'rol' => 'VENDEDOR'],
            ]);

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_usuario_se_normaliza_eliminando_espacios(): void
    {
        $this->crearUsuario('vendedor', 'VENDEDOR');

        $this->postJson('/api/login', ['usuario' => '  vendedor  ', 'password' => 'secreto123'])->assertOk();
    }

    public function test_contrasena_incorrecta_responde_mensaje_generico(): void
    {
        $this->crearUsuario('vendedor', 'VENDEDOR');

        $this->postJson('/api/login', ['usuario' => 'vendedor', 'password' => 'incorrecta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario' => 'Usuario o contraseña incorrectos.']);

        $this->assertGuest();
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion_y_recibe_el_mismo_mensaje(): void
    {
        $this->crearUsuario('vendedor', 'VENDEDOR', activo: false);

        $this->postJson('/api/login', ['usuario' => 'vendedor', 'password' => 'secreto123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario' => 'Usuario o contraseña incorrectos.']);

        $this->assertGuest();
    }

    public function test_campos_vacios_responden_mensajes_en_espanol(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'usuario' => 'Ingresa tu usuario.',
                'password' => 'Ingresa tu contraseña.',
            ]);
    }

    public function test_bloquea_tras_cinco_intentos_fallidos_y_libera_al_acertar(): void
    {
        $this->crearUsuario('vendedor', 'VENDEDOR');

        foreach (range(1, LoginRequest::MAX_INTENTOS) as $intento) {
            $this->postJson('/api/login', ['usuario' => 'vendedor', 'password' => "mal{$intento}"])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['usuario' => 'vendedor', 'password' => 'secreto123'])
            ->assertStatus(429)
            ->assertJsonPath('errors.usuario.0', fn (string $mensaje) => str_starts_with($mensaje, 'Demasiados intentos.'));

        $this->assertGuest();

        RateLimiter::clear($this->throttleKey('vendedor'));
        $this->postJson('/api/login', ['usuario' => 'vendedor', 'password' => 'secreto123'])->assertOk();
    }

    public function test_el_bloqueo_es_por_usuario_y_no_afecta_a_otros(): void
    {
        $this->crearUsuario('vendedor', 'VENDEDOR');
        $this->crearUsuario('admin', 'ADMINISTRADOR');

        foreach (range(1, LoginRequest::MAX_INTENTOS) as $intento) {
            $this->postJson('/api/login', ['usuario' => 'vendedor', 'password' => "mal{$intento}"]);
        }

        $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'secreto123'])->assertOk();
    }

    public function test_me_devuelve_el_usuario_autenticado_y_401_sin_sesion(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();

        $usuario = $this->crearUsuario('vendedor', 'VENDEDOR');

        $this->actingAs($usuario)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.usuario', 'vendedor')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.email');
    }

    public function test_logout_cierra_la_sesion(): void
    {
        $usuario = $this->crearUsuario('vendedor', 'VENDEDOR');

        $this->actingAs($usuario)->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');

        $this->assertGuest();
    }

    private function crearUsuario(string $usuario, string $rol, bool $activo = true): User
    {
        return User::factory()->create([
            'usuario' => $usuario,
            'password' => 'secreto123',
            'rol' => $rol,
            'activo' => $activo,
        ]);
    }

    private function throttleKey(string $usuario): string
    {
        return $usuario.'|127.0.0.1';
    }
}
