<?php

namespace App\Services;

use App\Models\Producto;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lógica de negocio del catálogo de productos.
 */
class ProductoService
{
    /**
     * Prefijo del código de producto generado por el sistema (RF-06).
     */
    public const PREFIJO_CODIGO = 'PRD-';

    /**
     * Registra un producto y, si corresponde, su movimiento de apertura.
     *
     * Todo ocurre en una única transacción; si cualquier paso falla, no se
     * persiste nada (CU-03 8a):
     *  1. Inserta el producto con un código temporal único.
     *  2. Asigna el código definitivo a partir del id autoincremental. Un id no
     *     se reasigna aunque el producto se desactive (RN-08), por lo que el
     *     código no se reutiliza; el índice UNIQUE garantiza su unicidad.
     *  3. Si la existencia inicial es mayor que cero, registra el movimiento
     *     APERTURA con saldo anterior 0 (CA-3, RN-02).
     *
     * @param  array<string, mixed>  $datos  Datos validados; la clave existencia_inicial es opcional.
     *
     * @throws DomainException Si la existencia es negativa o el código generado ya está ocupado.
     *
     * @see HU-03 Registro de productos
     */
    public function crear(array $datos): Producto
    {
        $existenciaInicial = (float) ($datos['existencia_inicial'] ?? 0);

        if ($existenciaInicial < 0) {
            throw new DomainException('La existencia inicial no puede ser negativa.');
        }

        unset($datos['existencia_inicial'], $datos['existencia_actual'], $datos['codigo']);

        return DB::transaction(function () use ($datos, $existenciaInicial): Producto {
            $producto = Producto::create([
                ...$datos,
                'codigo' => $this->codigoTemporal(),
                'existencia_actual' => $existenciaInicial,
                'activo' => true,
            ]);

            $this->asignarCodigoDefinitivo($producto);

            if ($existenciaInicial > 0) {
                $producto->movimientos()->create([
                    'fecha' => now(),
                    'tipo' => 'APERTURA',
                    'cantidad' => $existenciaInicial,
                    'saldo_anterior' => 0,
                    'saldo_resultante' => $existenciaInicial,
                    'referencia' => 'Apertura '.$producto->codigo,
                ]);
            }

            return $producto->load('categoria');
        });
    }

    /**
     * Actualiza los datos descriptivos del producto.
     *
     * El código y la existencia actual nunca se modifican por esta vía (RF-06, RN-02).
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Producto $producto, array $datos): Producto
    {
        unset($datos['codigo'], $datos['existencia_actual'], $datos['existencia_inicial']);

        $producto->update($datos);

        return $producto->load('categoria');
    }

    public function cambiarEstado(Producto $producto, bool $activo): Producto
    {
        $producto->update(['activo' => $activo]);

        return $producto->refresh();
    }

    public function descontarStock(Producto $producto, float $cantidad): Producto
    {
        return DB::transaction(function () use ($producto, $cantidad): Producto {
            $bloqueado = Producto::query()->lockForUpdate()->findOrFail($producto->getKey());

            if ($cantidad > (float) $bloqueado->existencia_actual) {
                throw new DomainException('La cantidad solicitada supera la existencia actual.');
            }

            $saldoAnterior = (float) $bloqueado->existencia_actual;
            $bloqueado->existencia_actual = $saldoAnterior - $cantidad;
            $bloqueado->save();

            return $bloqueado;
        });
    }

    /**
     * Formatea el código definitivo: prefijo y id con seis dígitos (PRD-000015).
     */
    public function generarCodigo(int $id): string
    {
        return self::PREFIJO_CODIGO.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Reemplaza el código temporal por el definitivo.
     *
     * La colisión solo es posible si existe un código con el mismo prefijo
     * registrado manualmente antes de HU-03; se traduce a un error de dominio.
     *
     * @throws DomainException
     */
    private function asignarCodigoDefinitivo(Producto $producto): void
    {
        $producto->codigo = $this->generarCodigo((int) $producto->getKey());

        try {
            $producto->save();
        } catch (UniqueConstraintViolationException) {
            throw new DomainException("El código {$producto->codigo} ya está en uso por otro producto. Contacte al administrador del sistema.");
        }
    }

    /**
     * Código provisional único; solo existe dentro de la transacción de alta.
     */
    private function codigoTemporal(): string
    {
        return 'TMP-'.Str::upper(Str::random(12));
    }
}
