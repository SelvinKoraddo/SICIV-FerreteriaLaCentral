<?php

namespace App\Http\Requests;

use App\Enums\UnidadMedida;
use App\Models\Producto;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de un producto en el catálogo.
 *
 * El código no se recibe del cliente porque lo genera el sistema (RF-06).
 * La existencia inicial solo se acepta durante el alta (RF-05, RN-02).
 *
 * @see HU-03 Registro de productos
 * @see CU-03 Registrar producto
 */
class StoreProductoRequest extends FormRequest
{
    /**
     * Valor máximo representable en columnas decimal(10,2).
     */
    protected const MAXIMO_DECIMAL = '99999999.99';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Producto::class) ?? false;
    }

    /**
     * La regla decimal:0,2 evita que MySQL redondee en silencio valores con más
     * de dos decimales (por ejemplo, 0.001 se almacenaría como 0.00).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'id_categoria' => ['required', 'integer', Rule::exists('categorias', 'id_categoria')->where('activa', true)],
            'unidad_medida' => ['required', 'string', Rule::in(UnidadMedida::valores())],
            'precio_venta' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'existencia_inicial' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAXIMO_DECIMAL, $this->reglaFracciones()],
            'existencia_minima' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAXIMO_DECIMAL, $this->reglaFracciones()],
        ];
    }

    /**
     * Datos validados y normalizados para la capa de servicio.
     *
     * @return array<string, mixed>
     */
    public function datosProducto(): array
    {
        $datos = $this->validated();
        $datos['existencia_inicial'] = (float) ($datos['existencia_inicial'] ?? 0);

        return $datos;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'id_categoria' => 'categoría',
            'unidad_medida' => 'unidad de medida',
            'precio_venta' => 'precio de venta',
            'existencia_inicial' => 'existencia inicial',
            'existencia_minima' => 'existencia mínima',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max.string' => 'El campo :attribute no debe exceder :max caracteres.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'decimal' => 'El campo :attribute admite como máximo 2 decimales.',
            'integer' => 'El campo :attribute no es válido.',
            'min.numeric' => 'El campo :attribute no puede ser negativo.',
            'max.numeric' => 'El campo :attribute excede el valor máximo permitido.',
            'precio_venta.gt' => 'El precio de venta debe ser mayor que cero.',
            'id_categoria.exists' => 'La categoría seleccionada no existe o está inactiva.',
            'unidad_medida.in' => 'La unidad de medida seleccionada no es válida.',
        ];
    }

    /**
     * Exige cantidades enteras cuando la unidad seleccionada no admite fracciones (RN-10).
     */
    protected function reglaFracciones(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                return;
            }

            $unidad = UnidadMedida::tryFrom((string) $this->input('unidad_medida'));

            if ($unidad !== null && ! $unidad->permiteFracciones() && floor((float) $value) !== (float) $value) {
                $fail("La unidad {$unidad->etiqueta()} no admite cantidades con decimales en :attribute.");
            }
        };
    }
}
