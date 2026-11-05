<?php

namespace App\Enums;

/**
 * Unidades de medida admitidas para los productos del catálogo.
 *
 * Cada unidad define si admite cantidades fraccionarias, de acuerdo con RN-10:
 * se pueden registrar 2.5 metros de cable, pero no 2.5 cajas de tornillos.
 *
 * @see HU-03 CA-1 Registro de productos
 * @see RN-10 Valores válidos
 */
enum UnidadMedida: string
{
    case UNIDAD = 'UNIDAD';
    case CAJA = 'CAJA';
    case PAR = 'PAR';
    case DOCENA = 'DOCENA';
    case JUEGO = 'JUEGO';
    case ROLLO = 'ROLLO';
    case BOLSA = 'BOLSA';
    case SACO = 'SACO';
    case METRO = 'METRO';
    case YARDA = 'YARDA';
    case PIE = 'PIE';
    case KILOGRAMO = 'KILOGRAMO';
    case LIBRA = 'LIBRA';
    case LITRO = 'LITRO';
    case GALON = 'GALON';

    /**
     * Nombre legible de la unidad para la interfaz de usuario.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::UNIDAD => 'Unidad',
            self::CAJA => 'Caja',
            self::PAR => 'Par',
            self::DOCENA => 'Docena',
            self::JUEGO => 'Juego',
            self::ROLLO => 'Rollo',
            self::BOLSA => 'Bolsa',
            self::SACO => 'Saco',
            self::METRO => 'Metro',
            self::YARDA => 'Yarda',
            self::PIE => 'Pie',
            self::KILOGRAMO => 'Kilogramo',
            self::LIBRA => 'Libra',
            self::LITRO => 'Litro',
            self::GALON => 'Galón',
        };
    }

    /**
     * Indica si la unidad admite cantidades con decimales.
     */
    public function permiteFracciones(): bool
    {
        return match ($this) {
            self::METRO, self::YARDA, self::PIE,
            self::KILOGRAMO, self::LIBRA,
            self::LITRO, self::GALON => true,
            default => false,
        };
    }

    /**
     * Valores aceptados por la regla de validación.
     *
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Catálogo de unidades serializable para el formulario del frontend.
     *
     * @return list<array{valor: string, etiqueta: string, permite_fracciones: bool}>
     */
    public static function opciones(): array
    {
        return array_map(static fn (self $unidad): array => [
            'valor' => $unidad->value,
            'etiqueta' => $unidad->etiqueta(),
            'permite_fracciones' => $unidad->permiteFracciones(),
        ], self::cases());
    }
}
