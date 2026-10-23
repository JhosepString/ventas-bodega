<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Representa una venta de un producto y su importe al momento de vender.
 */
final class Venta
{
    /**
     * Construye una venta con sus datos.
     *
     * @param int|null $id Identificador asignado al persistir; null antes del registro.
     * @param int $productoId Identificador del producto vendido.
     * @param int $cantidad Unidades vendidas.
     * @param float $precioUnitario Precio vigente por unidad al vender.
     * @param float $total Importe total de la venta.
     * @param string|null $fechaHora Fecha y hora asignadas por la base de datos; null antes del registro.
     * @param string|null $productoNombre Nombre del producto, incluido al consultar ventas.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $productoId,
        public readonly int $cantidad,
        public readonly float $precioUnitario,
        public readonly float $total,
        public readonly ?string $fechaHora,
        public readonly ?string $productoNombre = null
    ) {
    }
}
