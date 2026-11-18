<?php

namespace App\Http\Requests;

use App\Rules\CantidadSegunUnidad;
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
            // Pide una cantidad positiva, con hasta dos decimales y permitida por la unidad del producto.
            'detalles.*.cantidad' => [
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
                new CantidadSegunUnidad(),
            ],
            'detalles.*.costo_unitario' => [
                'required',
                'numeric',
                'gte:0',
            ],
        ];
    }
    
//Mensaje de error para cantidad decimal
    public function messages(): array
{
    return [
        'detalles.*.cantidad.decimal' =>
            'La cantidad debe tener como máximo 2 decimales.',
    ];
}

}