<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Venta;
use PDO;

/**
 * Ejecuta consultas preparadas para guardar y recuperar ventas.
 */
final class VentaRepository implements VentaRepositoryInterface
{
    /**
     * Recibe la conexión PDO que comparte la aplicación.
     *
     * @param PDO $pdo Conexión PDO configurada para lanzar excepciones.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserta una venta y devuelve el identificador generado.
     *
     * @param Venta $venta Datos de la venta que se insertará.
     *
     * @return int Identificador asignado por MySQL.
     */
    public function create(Venta $venta): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO venta (producto_id, cantidad, precio_unitario, total)
             VALUES (:producto_id, :cantidad, :precio_unitario, :total)'
        );
        $statement->execute([
            'producto_id' => $venta->productoId,
            'cantidad' => $venta->cantidad,
            'precio_unitario' => number_format($venta->precioUnitario, 2, '.', ''),
            'total' => number_format($venta->total, 2, '.', ''),
        ]);

        $id = $this->pdo->lastInsertId();

        if ($id === '0') {
            throw new \RuntimeException('La base de datos no devolvió el identificador de la venta creada.');
        }

        return (int) $id;
    }

    /**
     * Recupera las ventas registradas durante una fecha.
     *
     * @param string $fecha Fecha en formato AAAA-MM-DD.
     *
     * @return array<int, Venta> Ventas encontradas; vacío si no hubo.
     */
    public function findByFecha(string $fecha): array
    {
        $statement = $this->pdo->prepare(
            'SELECT venta.id, venta.producto_id, producto.nombre AS producto_nombre,
                    venta.cantidad, venta.precio_unitario, venta.total, venta.fecha_hora
             FROM venta
             INNER JOIN producto ON producto.id = venta.producto_id
             WHERE venta.fecha_hora >= :inicio
               AND venta.fecha_hora < :fin
             ORDER BY venta.fecha_hora, venta.id'
        );
        $statement->execute([
            'inicio' => $fecha . ' 00:00:00',
            'fin' => $this->fechaSiguiente($fecha) . ' 00:00:00',
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn (array $row): Venta => $this->mapVenta($row),
            $rows
        );
    }

    /**
     * Suma los totales de venta registrados durante una fecha.
     *
     * @param string $fecha Fecha en formato AAAA-MM-DD.
     *
     * @return float Total vendido; cero si no hubo ventas.
     */
    public function totalByFecha(string $fecha): float
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(total), 0)
             FROM venta
             WHERE fecha_hora >= :inicio
               AND fecha_hora < :fin'
        );
        $statement->execute([
            'inicio' => $fecha . ' 00:00:00',
            'fin' => $this->fechaSiguiente($fecha) . ' 00:00:00',
        ]);

        return (float) $statement->fetchColumn();
    }

    /**
     * Obtiene la fecha siguiente en formato AAAA-MM-DD.
     *
     * @param string $fecha Fecha inicial en formato AAAA-MM-DD.
     *
     * @return string Fecha del día siguiente.
     */
    private function fechaSiguiente(string $fecha): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($date === false || $date->format('Y-m-d') !== $fecha) {
            throw new \InvalidArgumentException('La fecha debe tener el formato AAAA-MM-DD y ser válida.');
        }

        return $date->modify('+1 day')->format('Y-m-d');
    }

    /**
     * Convierte una fila de MySQL en un modelo Venta.
     *
     * @param array<string, int|string> $row Fila asociativa devuelta por PDO.
     *
     * @return Venta Modelo construido con los datos persistidos.
     */
    private function mapVenta(array $row): Venta
    {
        return new Venta(
            (int) $row['id'],
            (int) $row['producto_id'],
            (int) $row['cantidad'],
            (float) $row['precio_unitario'],
            (float) $row['total'],
            (string) $row['fecha_hora'],
            (string) $row['producto_nombre']
        );
    }
}
