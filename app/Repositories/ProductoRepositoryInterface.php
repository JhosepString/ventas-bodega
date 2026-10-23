<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Producto;

/**
 * Define las operaciones de persistencia disponibles para productos.
 */
interface ProductoRepositoryInterface
{
    /**
     * Guarda un producto y devuelve el identificador generado.
     *
     * @param Producto $producto Datos del producto que se desea guardar.
     *
     * @return int Identificador asignado por la base de datos.
     */
    public function create(Producto $producto): int;

    /**
     * Busca un producto por su identificador.
     *
     * @param int $id Identificador que se desea consultar.
     *
     * @return Producto|null Producto encontrado o null si no existe.
     */
    public function findById(int $id): ?Producto;

    /**
     * Busca y bloquea un producto durante la transacción actual.
     *
     * @param int $id Identificador que se desea consultar.
     *
     * @return Producto|null Producto encontrado o null si no existe.
     */
    public function findByIdForUpdate(int $id): ?Producto;

    /**
     * Busca un producto por su código único.
     *
     * @param string $codigo Código del producto que se desea consultar.
     *
     * @return Producto|null Producto encontrado o null si no existe.
     */
    public function findByCodigo(string $codigo): ?Producto;

    /**
     * Obtiene todos los productos registrados.
     *
     * @return array<int, Producto> Productos encontrados; puede estar vacío.
     */
    public function findAll(): array;

    /**
     * Obtiene productos cuyo stock actual está en o por debajo del mínimo.
     *
     * @return array<int, Producto> Productos con stock bajo; puede estar vacío.
     */
    public function findStockBajo(): array;

    /**
     * Descuenta del stock la cantidad indicada.
     *
     * @param int $productoId Identificador del producto afectado.
     * @param int $cantidad Unidades que se descontarán.
     *
     * @return void
     */
    public function descontarStock(int $productoId, int $cantidad): void;
}
