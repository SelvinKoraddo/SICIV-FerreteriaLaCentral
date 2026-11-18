<?php

namespace App\Rules;

use App\Enums\UnidadMedida;
use App\Models\Producto;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class CantidadSegunUnidad implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    // Guarda los datos enviados para saber a qué producto pertenece cada cantidad.
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    // Revisa que la cantidad sea entera cuando la unidad del producto lo exige.
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $partes = explode('.', $attribute);
        $indice = $partes[1] ?? null;
        $idProducto = $this->data['detalles'][$indice]['id_producto'] ?? null;

        if (! is_scalar($idProducto) || ! ctype_digit((string) $idProducto)) {
            return;
        }

        $producto = Producto::query()->find($idProducto);

        if ($producto === null) {
            return;
        }

        $unidad = UnidadMedida::tryFrom($producto->unidad_medida);

        if (
            $unidad !== null &&
            ! $unidad->permiteFracciones() &&
            floor((float) $value) !== (float) $value
        ) {
            $fail("La unidad del producto '{$unidad->etiqueta()}' no admite cantidades fraccionarias.");
        }
    }
}
