<?php

namespace App\Enums;

use App\Models\Producto;

/**
 * Clasificación de la existencia de un producto respecto a su mínimo.
 *
 * - AGOTADO: saldo igual a cero.
 * - BAJO_MINIMO: saldo positivo, igual o inferior al mínimo configurado.
 * - NORMAL: saldo superior al mínimo.
 *
 * @see RN-04 Alertas
 * @see HU-07 Consulta de existencias
 */
enum NivelStock: string
{
    case AGOTADO = 'agotado';
    case BAJO_MINIMO = 'bajo_minimo';
    case NORMAL = 'normal';

    /**
     * Clasifica un producto a partir de sus saldos.
     */
    public static function deProducto(Producto $producto): self
    {
        return self::clasificar((float) $producto->existencia_actual, (float) $producto->existencia_minima);
    }

    public static function clasificar(float $existencia, float $minimo): self
    {
        return match (true) {
            $existencia <= 0 => self::AGOTADO,
            $existencia <= $minimo => self::BAJO_MINIMO,
            default => self::NORMAL,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::AGOTADO => 'Agotado',
            self::BAJO_MINIMO => 'Bajo mínimo',
            self::NORMAL => 'Normal',
        };
    }

    /**
     * Condición SQL equivalente a la clasificación, portable entre MySQL y SQLite.
     */
    public function condicionSql(): string
    {
        return match ($this) {
            self::AGOTADO => 'existencia_actual <= 0',
            self::BAJO_MINIMO => 'existencia_actual > 0 AND existencia_actual <= existencia_minima',
            self::NORMAL => 'existencia_actual > existencia_minima',
        };
    }

    /**
     * Indica si el nivel genera una alerta de reposición (RN-04).
     */
    public function enAlerta(): bool
    {
        return $this !== self::NORMAL;
    }

    /**
     * Condición SQL de los productos en alerta: agotados o en/bajo el mínimo (RN-04).
     */
    public static function condicionAlertaSql(): string
    {
        return 'existencia_actual <= existencia_minima OR existencia_actual <= 0';
    }

    /**
     * Expresión SQL que ordena de mayor a menor criticidad (0 = agotado, 2 = normal).
     */
    public static function ordenCriticidadSql(): string
    {
        return 'CASE WHEN existencia_actual <= 0 THEN 0 WHEN existencia_actual <= existencia_minima THEN 1 ELSE 2 END';
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
