<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Valida y ejecuta el inicio de sesión.
 *
 * Limita los intentos fallidos por usuario e IP para mitigar ataques de
 * fuerza bruta, y responde con un mensaje genérico para no revelar si el
 * usuario existe o está inactivo.
 *
 * @see HU-01 Inicio de sesión
 * @see RN-09 Autorización
 */
class LoginRequest extends FormRequest
{
    /**
     * Intentos fallidos permitidos antes del bloqueo temporal.
     */
    public const MAX_INTENTOS = 5;

    /**
     * Duración del bloqueo en segundos.
     */
    public const SEGUNDOS_BLOQUEO = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.required' => 'Ingresa tu usuario.',
            'usuario.max' => 'El usuario no debe exceder :max caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
        ];
    }

    /**
     * Intenta autenticar al usuario con las credenciales validadas.
     *
     * @throws ValidationException Si las credenciales son incorrectas o el usuario está bloqueado.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credenciales = [
            'usuario' => trim((string) $this->input('usuario')),
            'password' => (string) $this->input('password'),
            'activo' => true,
        ];

        if (! Auth::attempt($credenciales)) {
            RateLimiter::hit($this->throttleKey(), self::SEGUNDOS_BLOQUEO);

            throw ValidationException::withMessages([
                'usuario' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Rechaza la petición si se superó el número de intentos permitidos.
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_INTENTOS)) {
            return;
        }

        event(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'usuario' => "Demasiados intentos. Inténtalo de nuevo en {$segundos} segundos.",
        ])->status(429);
    }

    /**
     * Clave del limitador: combina usuario e IP para no bloquear a toda una red.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower(trim((string) $this->input('usuario'))).'|'.$this->ip());
    }
}
