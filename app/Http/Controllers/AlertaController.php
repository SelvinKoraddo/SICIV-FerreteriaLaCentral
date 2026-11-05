<?php

namespace App\Http\Controllers;

use App\Enums\NivelStock;
use App\Models\Producto;
use App\Queries\ProductoFiltro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alertas de existencia baja y agotados.
 *
 * Las alertas no se almacenan: se calculan a partir del saldo vigente de cada
 * producto activo. Así se activan en cuanto una venta deja la existencia en o
 * bajo el mínimo (CA-1) y se cierran cuando una entrada la supera (CA-4), sin
 * depender de procesos adicionales ni de que se sincronice otra tabla.
 *
 * @see HU-08 Alertas de existencia baja y agotados
 * @see CU-08 Consultar alertas de existencia baja
 * @see RN-04 Alertas
 */
class AlertaController extends Controller
{
    private const PAGINA_DEFECTO = 100;

    private const PAGINA_MAXIMA = 500;

    /**
     * Listado de productos en alerta, agotados primero (CA-2), con filtro por
     * categoría, búsqueda y nivel (CU-08 paso 5).
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = min(max((int) $request->query('per_page', self::PAGINA_DEFECTO), 1), self::PAGINA_MAXIMA);

        $alertas = $this->consulta($request, aplicarNivel: true)
            ->with('categoria:id_categoria,nombre')
            ->orderByRaw(NivelStock::ordenCriticidadSql())
            ->orderByRaw('(existencia_actual - existencia_minima)')
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->through(fn (Producto $producto): array => $this->fila($producto));

        return response()->json($alertas);
    }

    /**
     * Número de alertas activas por nivel, para el aviso al iniciar sesión y el
     * indicador del menú (CA-3). Se resuelve en una sola consulta agregada.
     */
    public function conteo(Request $request): JsonResponse
    {
        $totales = $this->consulta($request, aplicarNivel: false)
            ->selectRaw(implode(', ', [
                'COUNT(*) AS total',
                'SUM(CASE WHEN '.NivelStock::AGOTADO->condicionSql().' THEN 1 ELSE 0 END) AS agotado',
                'SUM(CASE WHEN '.NivelStock::BAJO_MINIMO->condicionSql().' THEN 1 ELSE 0 END) AS bajo_minimo',
            ]))
            ->toBase()
            ->first();

        return response()->json([
            'total' => (int) ($totales->total ?? 0),
            'agotado' => (int) ($totales->agotado ?? 0),
            'bajo_minimo' => (int) ($totales->bajo_minimo ?? 0),
        ]);
    }

    /**
     * Productos activos en alerta con los filtros de la petición.
     *
     * @return Builder<Producto>
     */
    private function consulta(Request $request, bool $aplicarNivel): Builder
    {
        $nivel = $aplicarNivel ? NivelStock::tryFrom((string) $request->query('nivel')) : null;

        return ProductoFiltro::aplicar(Producto::query(), [
            'search' => $request->query('search'),
            'categoria' => $request->query('categoria'),
            'estado' => 'activos',
            'nivel' => $nivel?->enAlerta() ? $nivel->value : null,
        ])->whereRaw('('.NivelStock::condicionAlertaSql().')');
    }

    /**
     * Representación de una alerta (CU-08 paso 4).
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
            'unidad_medida' => $producto->unidad_medida,
            'existencia_actual' => $existencia,
            'existencia_minima' => $minimo,
            // Cantidad mínima a reponer para salir de la alerta (superar el mínimo).
            'faltante' => round(max($minimo - $existencia, 0), 2),
            'nivel' => $nivel->value,
            'nivel_etiqueta' => $nivel->etiqueta(),
        ];
    }
}
