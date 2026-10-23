<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Representa un producto registrado en el minimarket.
 */
final class Producto
{
    /**
     * Construye un producto con los datos recibidos.
     *
     * @param int|null $id Identificador asignado por la base de datos; es null antes de persistir.
     * @param string $codigo Código único del producto.
     * @param string $nombre Nombre legible del producto.
     * @param string $categoria Categoría a la que pertenece el producto.
     * @param float $precioVenta Precio vigente de venta, expresado en soles.
     * @param int $stockActual Unidades disponibles actualmente.
     * @param int $stockMinimo Umbral para identificar stock bajo.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $categoria,
        public readonly float $precioVenta,
        public readonly int $stockActual,
        public readonly int $stockMinimo
    ) {
    }
}
