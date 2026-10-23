<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\TransaccionInterface;
use App\Exceptions\DatabaseException;
use App\Exceptions\NoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Models\Venta;
use App\Repositories\ProductoRepositoryInterface;
use App\Repositories\VentaRepositoryInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Aplica las reglas y coordina las operaciones de venta.
 */
final class VentaService
{
    /**
     * Recibe los repositorios y el controlador de transacciones.
     *
     * @param ProductoRepositoryInterface $productoRepository Persistencia de productos.
     * @param VentaRepositoryInterface $ventaRepository Persistencia de ventas.
     * @param TransaccionInterface $transaccion Operaciones transaccionales, sustituible en pruebas.
     */
    public function __construct(
        private readonly ProductoRepositoryInterface $productoRepository,
        private readonly VentaRepositoryInterface $ventaRepository,
        private readonly TransaccionInterface $transaccion
    ) {
    }

    /**
     * Registra una venta y descuenta el stock dentro de una transacción.
     *
     * @param int $productoId Identificador del producto vendido.
     * @param int $cantidad Cantidad entera solicitada.
     *
     * @return Venta Venta registrada con el precio vigente.
     *
     * @throws ValidacionException Si la cantidad no es positiva o supera el stock.
     * @throws NoEncontradoException Si el producto no existe.
     * @throws DatabaseException Si falla la persistencia o la transacción.
     */
    public function registrar(int $productoId, int $cantidad): Venta
    {
        if ($cantidad <= 0) {
            throw new ValidacionException('La cantidad vendida debe ser mayor que cero.');
        }

        $transaccionIniciada = false;

        try {
            $this->transaccion->begin();
            $transaccionIniciada = true;

            $producto = $this->productoRepository->findByIdForUpdate($productoId);

            if ($producto === null) {
                throw new NoEncontradoException('El producto no existe.');
            }

            if ($cantidad > $producto->stockActual) {
                throw new ValidacionException('No se puede vender más de lo que hay en stock.');
            }

            $precioUnitario = $producto->precioVenta;
            $total = round($precioUnitario * $cantidad, 2);
            $ventaPendiente = new Venta(
                null,
                $productoId,
                $cantidad,
                $precioUnitario,
                $total,
                null,
                $producto->nombre
            );

            $ventaId = $this->ventaRepository->create($ventaPendiente);
            $this->productoRepository->descontarStock($productoId, $cantidad);
            $this->transaccion->commit();
            $transaccionIniciada = false;

            return new Venta(
                $ventaId,
                $productoId,
                $cantidad,
                $precioUnitario,
                $total,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                $producto->nombre
            );
        } catch (Throwable $exception) {
            if ($transaccionIniciada) {
                try {
                    $this->transaccion->rollBack();
                } catch (Throwable $rollbackException) {
                    error_log('Falló el rollback de una venta: ' . $rollbackException->getMessage());
                }
            }

            if (
                $exception instanceof ValidacionException
                || $exception instanceof NoEncontradoException
                || $exception instanceof DatabaseException
            ) {
                throw $exception;
            }

            throw new DatabaseException('No se pudo registrar la venta. Inténtelo nuevamente.', 0, $exception);
        }
    }

    /**
     * Devuelve las ventas del día actual.
     *
     * @return array<int, Venta> Ventas registradas hoy; vacío si no hay.
     */
    public function ventasDelDia(): array
    {
        return $this->ventaRepository->findByFecha(date('Y-m-d'));
    }

    /**
     * Devuelve el total vendido durante el día actual.
     *
     * @return float Total vendido, o cero si no hay ventas.
     */
    public function totalDelDia(): float
    {
        return $this->ventaRepository->totalByFecha(date('Y-m-d'));
    }
}
