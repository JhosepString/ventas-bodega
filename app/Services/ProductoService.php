<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Producto;
use App\Repositories\ProductoRepositoryInterface;
use App\Exceptions\ValidacionException;
use PDOException;

/**
 * Aplica las reglas de negocio relacionadas con productos.
 */
final class ProductoService
{
    /**
     * Recibe el repositorio de productos.
     *
     * @param ProductoRepositoryInterface $repository Acceso a persistencia de productos.
     */
    public function __construct(private readonly ProductoRepositoryInterface $repository)
    {
    }

    /**
     * Valida y registra un producto.
     *
     * @param Producto $producto Datos del producto que se desea registrar.
     *
     * @return int Identificador asignado al nuevo producto.
     *
     * @throws ValidacionException Si el precio, el stock o el código no son válidos.
     */
    public function crear(Producto $producto): int
    {
        if ($producto->precioVenta < 0) {
            throw new ValidacionException('El precio de venta no puede ser negativo.');
        }

        if ($producto->stockActual < 0 || $producto->stockMinimo < 0) {
            throw new ValidacionException('El stock actual y el stock mínimo no pueden ser negativos.');
        }

        if ($this->repository->findByCodigo($producto->codigo) !== null) {
            throw new ValidacionException('El código de producto debe ser único.');
        }

        try {
            return $this->repository->create($producto);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new ValidacionException('El código de producto debe ser único.', 0, $exception);
            }

            throw $exception;
        }
    }

    /**
     * Devuelve todos los productos registrados.
     *
     * @return array<int, Producto> Productos; vacío si aún no hay registros.
     */
    public function listar(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Devuelve productos cuyo stock actual está en o por debajo del mínimo.
     *
     * @return array<int, Producto> Productos con stock bajo; vacío si no hay.
     */
    public function listarStockBajo(): array
    {
        return $this->repository->findStockBajo();
    }
}
