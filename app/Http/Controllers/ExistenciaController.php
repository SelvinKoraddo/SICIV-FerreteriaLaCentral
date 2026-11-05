<?php

namespace App\Http\Controllers;

use App\Enums\NivelStock;
use App\Models\Producto;
use App\Queries\ProductoFiltro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de existencias del inventario.
 *
 * Solo considera productos activos: las alertas de RN-04 aplican únicamente a
 * ellos y los inactivos no participan en nuevas operaciones (RN-08).
 * Los tres roles pueden consultar existencias (RN-09).
 *
 * @see HU-07 Consulta de existencias
 */
class ExistenciaController extends Controller
{
    /**
     * Tamaño de página por defecto y máximo permitido.
     */
    private const PAGINA_DEFECTO = 100;

    private const PAGINA_MAXIMA = 500;

    /**
     * Lista las existencias con filtros por producto, categoría y nivel (CA-1),
     * incluyendo la diferencia respecto al mínimo (CA-2). Los productos más
     * críticos aparecen primero.
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = min(max((int) $request->query('per_page', self::PAGINA_DEFECTO), 1), self::PAGINA_MAXIMA);

        $existencias = $this->consulta($request)
            ->with('categoria:id_categoria,nombre')
            ->orderByRaw(NivelStock::ordenCriticidadSql())
            ->orderByRaw('(existencia_actual - existencia_minima)')
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->through(fn (Producto $producto): array => $this->fila($producto));

        return response()->json($existencias);
    }

    /**
     * Totales por nivel de stock para los mismos filtros del listado, calculados
     * en una sola consulta agregada.
     */
    public function resumen(Request $request): JsonResponse
    {
        $totales = $this->consulta($request)
            ->selectRaw(implode(', ', [
                'COUNT(*) AS total',
                'SUM(CASE WHEN '.NivelStock::AGOTADO->condicionSql().' THEN 1 ELSE 0 END) AS agotado',
                'SUM(CASE WHEN '.NivelStock::BAJO_MINIMO->condicionSql().' THEN 1 ELSE 0 END) AS bajo_minimo',
                'SUM(CASE WHEN '.NivelStock::NORMAL->condicionSql().' THEN 1 ELSE 0 END) AS normal',
            ]))
            ->toBase()
            ->first();

        return response()->json([
            'total' => (int) ($totales->total ?? 0),
            'agotado' => (int) ($totales->agotado ?? 0),
            'bajo_minimo' => (int) ($totales->bajo_minimo ?? 0),
            'normal' => (int) ($totales->normal ?? 0),
            'en_alerta' => (int) ($totales->agotado ?? 0) + (int) ($totales->bajo_minimo ?? 0),
            'consultado_en' => now('America/El_Salvador')->toIso8601String(),
        ]);
    }

    /**
     * Consulta base: productos activos con los filtros de la petición
     * (el nivel se aplica solo al listado, no al resumen).
     *
     * @return Builder<Producto>
     */
    private function consulta(Request $request): Builder
    {
        $filtros = [
            'search' => $request->query('search'),
            'categoria' => $request->query('categoria'),
            'estado' => 'activos',
            'nivel' => $request->routeIs('existencias.resumen') ? null : $request->query('nivel'),
        ];

        return ProductoFiltro::aplicar(Producto::query(), $filtros);
    }

    /**
     * Representación de una fila de existencias (CA-2).
     *
     * @return array<string, mixed>
     */
    private function fila(Producto $producto): array
    {
        $existencia = (float) $producto->existencia_actual;
        $minimo = (float) $producto->existencia_minima;
        $nivel = NivelStock::clasificar($existencia, $minimo);

        return [
            'id_producto' => $producto->id_producto,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'categoria' => $producto->categoria?->nombre,
            'id_categoria' => $producto->id_categoria,
            'unidad_medida' => $producto->unidad_medida,
            'existencia_actual' => $existencia,
            'existencia_minima' => $minimo,
            'diferencia' => round($existencia - $minimo, 2),
            'nivel' => $nivel->value,
            'nivel_etiqueta' => $nivel->etiqueta(),
        ];
    }
}
