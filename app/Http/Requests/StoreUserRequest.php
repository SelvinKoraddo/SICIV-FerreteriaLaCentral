<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'ADMINISTRADOR';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,usuario'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', Rule::in(['ADMINISTRADOR', 'VENDEDOR', 'ENCARGADO_INVENTARIO'])],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
