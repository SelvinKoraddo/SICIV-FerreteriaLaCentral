<?php

namespace App\Http\Requests;

use App\Enums\UnidadMedida;
use App\Models\Producto;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Valida la edición de un producto existente.
 *
 * No admite código ni existencia: el código es inmutable (RF-06) y el saldo
 * solo cambia mediante movimientos de inventario (RN-02). Como validated()
 * únicamente devuelve los campos con reglas, cualquier otro dato se descarta.
 *
 * @see HU-03 Registro de productos
 * @see RF-05 Mantenimiento de productos
 */
class UpdateProductoRequest extends StoreProductoRequest
{
    public function authorize(): bool
    {
        $producto = $this->producto();

        return $producto !== null && ($this->user()?->can('update', $producto) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $reglas = parent::rules();
        unset($reglas['existencia_inicial']);

        // RN-03 aplica a altas y reasignaciones: se permite conservar la
        // categoría actual aunque haya sido desactivada después del alta.
        $idCategoriaActual = (int) $this->producto()?->id_categoria;
        $conservaCategoria = (int) $this->input('id_categoria') === $idCategoriaActual;

        $reglas['id_categoria'] = [
            'required',
            'integer',
            Rule::exists('categorias', 'id_categoria')->when(! $conservaCategoria, fn ($regla) => $regla->where('activa', true)),
        ];

        $reglas['unidad_medida'][] = $this->reglaCambioDeUnidad();

        return $reglas;
    }

    /**
     * @return array<string, mixed>
     */
    public function datosProducto(): array
    {
        return $this->validated();
    }

    /**
     * Impide cambiar a una unidad sin fracciones si la existencia actual tiene
     * decimales, porque el saldo resultante no sería representable (RN-10).
     */
    protected function reglaCambioDeUnidad(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $unidad = UnidadMedida::tryFrom((string) $value);
            $existencia = (float) ($this->producto()?->existencia_actual ?? 0);

            if ($unidad !== null && ! $unidad->permiteFracciones() && floor($existencia) !== $existencia) {
                $fail("No se puede cambiar a {$unidad->etiqueta()}: la existencia actual ({$existencia}) tiene decimales y esa unidad no los admite.");
            }
        };
    }

    /**
     * Producto resuelto por el route model binding.
     */
    private function producto(): ?Producto
    {
        $producto = $this->route('producto');

        return $producto instanceof Producto ? $producto : null;
    }
}
