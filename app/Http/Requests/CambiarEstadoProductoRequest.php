<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la activación o desactivación de un producto.
 *
 * Los productos no se eliminan: se desactivan para conservar su historial
 * de movimientos, ventas y entradas (RN-08).
 *
 * @see RN-08 Conservación del historial
 */
class CambiarEstadoProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $producto = $this->route('producto');

        return $producto instanceof Producto && ($this->user()?->can('update', $producto) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activo.required' => 'Debe indicar el nuevo estado del producto.',
            'activo.boolean' => 'El estado del producto no es válido.',
        ];
    }
}
