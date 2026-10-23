<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Venta;

/**
 * Define las operaciones de persistencia disponibles para las ventas.
 */
interface VentaRepositoryInterface
{
    /**
     * Guarda una venta y devuelve el identificador generado.
     *
     * @param Venta $venta Datos de la venta que se desea registrar.
     *
     * @return int Identificador asignado por la base de datos.
     */
    public function create(Venta $venta): int;

    /**
     * Busca las ventas registradas dentro de una fecha determinada.
     *
     * @param string $fecha Fecha en formato AAAA-MM-DD.
     *
     * @return array<int, Venta> Ventas del día; puede estar vacío.
     */
    public function findByFecha(string $fecha): array;

    /**
     * Calcula la suma de los totales de venta para una fecha.
     *
     * @param string $fecha Fecha en formato AAAA-MM-DD.
     *
     * @return float Total vendido; cero si no hubo ventas.
     */
    public function totalByFecha(string $fecha): float;
}
