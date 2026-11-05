<?php

namespace App\Queries;

use App\Enums\NivelStock;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros de consulta del catálogo de productos.
 *
 * Centraliza los criterios que comparten el listado de productos, la consulta
 * de existencias y la exportación, de modo que todos devuelvan el mismo
 * conjunto de datos para los mismos parámetros.
 *
 * Parámetros admitidos:
 *  - search:    coincidencia parcial en código, nombre o categoría.
 *  - categoria: id de la categoría.
 *  - estado:    activos | inactivos (sin valor: todos). "activos=1" se acepta
 *               por compatibilidad con las vistas existentes.
 *  - nivel:     agotado | bajo_minimo | normal (ver NivelStock).
 */
final class ProductoFiltro
{
    /**
     * Construye la consulta base aplicando los filtros presentes en la petición.
     *
     * @return Builder<Producto>
     */
    public static function desdePeticion(Request $request): Builder
    {
        return self::aplicar(Producto::query(), [
            'search' => $request->query('search'),
            'categoria' => $request->query('categoria'),
            'estado' => $request->boolean('activos') ? 'activos' : $request->query('estado'),
            'nivel' => $request->query('nivel'),
        ]);
    }

    /**
     * @param  Builder<Producto>  $consulta
     * @param  array{search?: ?string, categoria?: mixed, estado?: ?string, nivel?: ?string}  $filtros
     * @return Builder<Producto>
     */
    public static function aplicar(Builder $consulta, array $filtros): Builder
    {
        $search = trim((string) ($filtros['search'] ?? ''));
        $categoria = $filtros['categoria'] ?? null;
        $estado = $filtros['estado'] ?? null;
        $nivel = NivelStock::tryFrom((string) ($filtros['nivel'] ?? ''));

        return $consulta
            ->when($estado === 'activos', fn (Builder $query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn (Builder $query) => $query->where('activo', false))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $productQuery) use ($search): void {
                    $productQuery
                        ->where('codigo', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%")
                        ->orWhereHas('categoria', fn (Builder $categoryQuery) => $categoryQuery->where('nombre', 'like', "%{$search}%"));
                });
            })
            ->when($categoria !== null && $categoria !== '', fn (Builder $query) => $query->where('id_categoria', $categoria))
            ->when($nivel !== null, fn (Builder $query) => $query->whereRaw("({$nivel->condicionSql()})"));
    }
}
