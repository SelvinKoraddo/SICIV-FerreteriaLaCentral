<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultaInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->activo ?? false)
            && in_array($this->user()?->rol, ['ADMINISTRADOR', 'ENCARGADO_INVENTARIO', 'VENDEDOR'], true);
    }

    // Revisa los filtros para no buscar con fechas o cantidades equivocadas.
    public function rules(): array
    {
        return [
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', ...($this->filled('fecha_desde') ? ['after_or_equal:fecha_desde'] : [])],
            'categoria' => ['nullable', 'integer', 'exists:categorias,id_categoria'],
            'producto' => ['nullable', 'integer', 'exists:productos,id_producto'],
            'tipo' => ['nullable', Rule::in(['APERTURA', 'RECEPCION', 'VENTA'])],
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_desde.date_format' => 'La fecha inicial no es válida.',
            'fecha_hasta.date_format' => 'La fecha final no es válida.',
            'fecha_hasta.after_or_equal' => 'La fecha inicial no puede ser posterior a la final.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El campo :attribute seleccionado no existe.',
            'tipo.in' => 'Selecciona un tipo de movimiento válido.',
            'min' => 'El campo :attribute debe ser como mínimo :min.',
            'max' => 'El campo :attribute debe ser como máximo :max.',
        ];
    }

    public function attributes(): array
    {
        return ['categoria' => 'categoría', 'producto' => 'producto', 'limite' => 'límite', 'page' => 'página', 'per_page' => 'registros por página'];
    }
}
