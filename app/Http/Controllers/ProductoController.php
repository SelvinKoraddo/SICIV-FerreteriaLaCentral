<?php

namespace App\Http\Controllers;

use App\Enums\UnidadMedida;
use App\Exports\CatalogoProductosCsv;
use App\Http\Requests\CambiarEstadoProductoRequest;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\Producto;
use App\Queries\ProductoFiltro;
use App\Services\ProductoService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoController extends Controller
{
    public function __construct(private readonly ProductoService $service) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->consultaFiltrada($request)
            ->with('categoria')
            ->latest('id_producto')
            ->paginate((int) $request->query('per_page', 100));

        return response()->json($products);
    }

    /**
     * Descarga el catálogo en CSV aplicando los mismos filtros del listado.
     *
     * @see HU-03 Registro de productos
     */
    public function exportar(Request $request, CatalogoProductosCsv $exportacion): StreamedResponse
    {
        return $exportacion->descargar($this->consultaFiltrada($request));
    }

    /**
     * Lista las unidades de medida disponibles para el formulario de productos.
     *
     * @see HU-03 CA-1
     */
    public function unidadesMedida(): JsonResponse
    {
        return response()->json(UnidadMedida::opciones());
    }

    /**
     * Registra un producto y responde con el código asignado (HU-03 CA-4).
     *
     * Responde 409 si el código generado entra en conflicto con un registro previo.
     */
    public function store(StoreProductoRequest $request): JsonResponse
    {
        try {
            $producto = $this->service->crear($request->datosProducto());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => "Producto registrado con el código {$producto->codigo}.",
            'producto' => $producto,
        ], 201);
    }

    public function show(Producto $producto): JsonResponse
    {
        return response()->json($producto->load('categoria'));
    }

    /**
     * Actualiza los datos descriptivos de un producto sin alterar código ni existencia.
     */
    public function update(UpdateProductoRequest $request, Producto $producto): JsonResponse
    {
        $producto = $this->service->actualizar($producto, $request->datosProducto());

        return response()->json([
            'message' => "Producto {$producto->codigo} actualizado.",
            'producto' => $producto,
        ]);
    }

    /**
     * Activa o desactiva un producto sin eliminarlo (RN-08).
     */
    public function cambiarEstado(CambiarEstadoProductoRequest $request, Producto $producto): JsonResponse
    {
        $producto = $this->service->cambiarEstado($producto, $request->boolean('activo'));
        $accion = $producto->activo ? 'activado' : 'desactivado';

        return response()->json([
            'message' => "Producto {$producto->codigo} {$accion}.",
            'producto' => $producto->load('categoria'),
        ]);
    }

    /**
     * Consulta de productos con los filtros de la petición (ver ProductoFiltro).
     *
     * @return Builder<Producto>
     */
    private function consultaFiltrada(Request $request): Builder
    {
        return ProductoFiltro::desdePeticion($request);
    }

    public function destroy(Producto $producto): JsonResponse
    {
        $this->authorize('delete', $producto);
        $this->service->cambiarEstado($producto, false);

        return response()->json(['message' => 'Producto desactivado.']);
    }
}
