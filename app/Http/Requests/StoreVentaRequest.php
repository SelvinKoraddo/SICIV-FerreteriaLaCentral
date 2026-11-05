<?php

namespace App\Http\Requests;

use App\Models\Venta;
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
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
