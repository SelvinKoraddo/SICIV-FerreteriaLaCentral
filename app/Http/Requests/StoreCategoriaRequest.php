<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol, ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'], true);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50', 'unique:categorias,nombre'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
