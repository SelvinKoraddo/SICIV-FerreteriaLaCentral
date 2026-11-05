<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'ADMINISTRADOR';
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'usuario')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'rol' => ['required', Rule::in(['ADMINISTRADOR', 'VENDEDOR', 'ENCARGADO_INVENTARIO'])],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
