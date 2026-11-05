<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol, ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'], true);
    }

    public function rules(): array
    {
        $proveedor = $this->route('proveedor');

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'nit' => [
                'required',
                'string',
                'max:20',
                Rule::unique('proveedores', 'nit')->ignore($proveedor->id_proveedor),
            ],
            'telefono' => ['nullable', 'string', 'max:15'],
            'correo' => ['nullable', 'email', 'max:100'],
            'direccion' => ['nullable', 'string'],
        ];
    }
}
