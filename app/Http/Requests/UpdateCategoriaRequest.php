<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol, ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'], true);
    }

    public function rules(): array
    {
        /** @var Categoria $categoria */
        $categoria = $this->route('categoria');

        return [
            'nombre' => ['required', 'string', 'max:50', Rule::unique('categorias', 'nombre')->ignore($categoria)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}