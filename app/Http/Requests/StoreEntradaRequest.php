<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->rol,
            ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO'],
            true
        );
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'referencia' => ['nullable', 'string', 'max:50'],
            'id_proveedor' => [
                'required',
                'integer',
                Rule::exists('proveedores', 'id_proveedor')
                    ->where('activo', true),
            ],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id_producto')
                    ->where('activo', true),
            ],
            'detalles.*.cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'detalles.*.costo_unitario' => [
                'required',
                'numeric',
                'gte:0',
            ],
        ];
    }
}