<?php

namespace App\Http\Requests;

use App\Models\Venta;
use App\Rules\CantidadSegunUnidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Venta::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'numero' => ['nullable', 'string', 'max:20', 'unique:ventas,numero'],
            'fecha' => ['required', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', Rule::exists('productos', 'id_producto')->where('activo', true)],
            // Pide una cantidad positiva, con hasta dos decimales y permitida por la unidad del producto.
            'detalles.*.cantidad' => ['required', 'numeric', 'decimal:0,2', 'gt:0', new CantidadSegunUnidad()],
        ];
    }
}
