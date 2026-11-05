<?php

namespace App\Exports;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta el catálogo de productos a un archivo CSV compatible con Excel.
 *
 * - Se escribe en streaming y por bloques, de modo que el consumo de memoria
 *   no depende del tamaño del catálogo.
 * - Incluye BOM UTF-8 para que Excel muestre correctamente tildes y eñes.
 * - Neutraliza fórmulas en los campos de texto (inyección CSV / OWASP).
 *
 * @see HU-03 Registro de productos
 */
class CatalogoProductosCsv
{
    /**
     * Cantidad de registros leídos por bloque.
     */
    private const TAMANO_BLOQUE = 500;

    /**
     * Caracteres con los que Excel interpreta una celda como fórmula.
     */
    private const INICIO_FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Encabezados de las columnas del archivo.
     *
     * @return list<string>
     */
    public function encabezados(): array
    {
        return [
            'Código',
            'Nombre',
            'Descripción',
            'Categoría',
            'Unidad de medida',
            'Precio de venta (USD)',
            'Existencia actual',
            'Existencia mínima',
            'Nivel de stock',
            'Estado',
        ];
    }

    /**
     * Genera la respuesta de descarga a partir de una consulta ya filtrada.
     *
     * @param  Builder<Producto>  $consulta
     */
    public function descargar(Builder $consulta): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($consulta): void {
                $salida = fopen('php://output', 'wb');
                fwrite($salida, "\xEF\xBB\xBF");
                $this->escribirFila($salida, $this->encabezados());

                $consulta->with('categoria')
                    ->lazyById(self::TAMANO_BLOQUE, 'id_producto')
                    ->each(fn (Producto $producto) => $this->escribirFila($salida, $this->fila($producto)));

                fclose($salida);
            },
            $this->nombreArchivo(),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Convierte un producto en la fila del archivo.
     *
     * @return list<string>
     */
    public function fila(Producto $producto): array
    {
        return [
            $producto->codigo,
            $this->texto($producto->nombre),
            $this->texto($producto->descripcion),
            $this->texto($producto->categoria?->nombre),
            $producto->unidad_medida,
            number_format((float) $producto->precio_venta, 2, '.', ''),
            number_format((float) $producto->existencia_actual, 2, '.', ''),
            number_format((float) $producto->existencia_minima, 2, '.', ''),
            $this->nivelStock($producto),
            $producto->activo ? 'Activo' : 'Inactivo',
        ];
    }

    /**
     * Nombre del archivo con la fecha y hora local de El Salvador.
     */
    public function nombreArchivo(): string
    {
        return 'catalogo_productos_'.now('America/El_Salvador')->format('Ymd_Hi').'.csv';
    }

    /**
     * Clasificación de existencias según RN-04.
     */
    private function nivelStock(Producto $producto): string
    {
        $actual = (float) $producto->existencia_actual;

        return match (true) {
            $actual <= 0 => 'Agotado',
            $actual <= (float) $producto->existencia_minima => 'Bajo mínimo',
            default => 'Normal',
        };
    }

    /**
     * Antepone un apóstrofo a los textos que Excel ejecutaría como fórmula.
     */
    private function texto(?string $valor): string
    {
        $valor = (string) $valor;

        return $valor !== '' && in_array($valor[0], self::INICIO_FORMULA, true) ? "'".$valor : $valor;
    }

    /**
     * Escribe una fila conforme a RFC 4180 (sin carácter de escape adicional).
     *
     * @param  resource  $salida
     * @param  list<string>  $columnas
     */
    private function escribirFila($salida, array $columnas): void
    {
        fputcsv($salida, $columnas, ',', '"', '');
    }
}
