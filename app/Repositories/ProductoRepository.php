<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Producto;
use PDO;

/**
 * Ejecuta consultas preparadas para recuperar y guardar productos.
 */
final class ProductoRepository implements ProductoRepositoryInterface
{
    /**
     * Recibe la conexión PDO compartida por la aplicación.
     *
     * @param PDO $pdo Conexión PDO configurada para lanzar excepciones.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserta un producto y devuelve el identificador generado.
     *
     * @param Producto $producto Datos del producto que se insertará.
     *
     * @return int Identificador asignado por MySQL.
     */
    public function create(Producto $producto): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO producto (codigo, nombre, categoria, precio_venta, stock_actual, stock_minimo)
             VALUES (:codigo, :nombre, :categoria, :precio_venta, :stock_actual, :stock_minimo)'
        );
        $statement->execute([
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'categoria' => $producto->categoria,
            'precio_venta' => number_format($producto->precioVenta, 2, '.', ''),
            'stock_actual' => $producto->stockActual,
            'stock_minimo' => $producto->stockMinimo,
        ]);

        $id = $this->pdo->lastInsertId();

        if ($id === '0') {
            throw new \RuntimeException('La base de datos no devolvió el identificador del producto creado.');
        }

        return (int) $id;
    }

    /**
     * Busca un producto por su identificador.
     *
     * @param int $id Identificador que se consultará.
     *
     * @return Producto|null Producto encontrado o null si no existe.
     */
    public function findById(int $id): ?Producto
    {
        $statement = $this->pdo->prepare(
            'SELECT id, codigo, nombre, categoria, precio_venta, stock_actual, stock_minimo
             FROM producto
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapProducto($row);
    }

    /**
     * Busca y bloquea un producto hasta finalizar la transacción actual.
     *
     * @param int $id Identificador que se desea consultar.
     *
     * @return Producto|null Producto encontrado o null si no existe.
     */
    public function findByIdForUpdate(int $id): ?Producto
    {
        $statement = $this->pdo->prepare(
            'SELECT id, codigo, nombre, categoria, precio_venta, stock_actual, stock_minimo
             FROM producto
             WHERE id = :id
             FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapProducto($row);
    }

    /**
     * Busca un producto por su código único.
     *
     * @param string $codigo Código del producto que se consultará.
     *
     * @return Producto|null Producto encontrado o null si no existe.
     */
    public function findByCodigo(string $codigo): ?Producto
    {
        $statement = $this->pdo->prepare(
            'SELECT id, codigo, nombre, categoria, precio_venta, stock_actual, stock_minimo
             FROM producto
             WHERE codigo = :codigo'
        );
        $statement->execute(['codigo' => $codigo]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->mapProducto($row);
    }

    /**
     * Recupera todos los productos ordenados por código.
     *
     * @return array<int, Producto> Productos registrados; vacío si no existen.
     */
    public function findAll(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, codigo, nombre, categoria, precio_venta, stock_actual, stock_minimo
             FROM producto
             ORDER BY codigo'
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn (array $row): Producto => $this->mapProducto($row),
            $rows
        );
    }

    /**
     * Recupera productos cuyo stock actual está en o por debajo del mínimo.
     *
     * @return array<int, Producto> Productos con stock bajo; vacío si no hay.
     */
    public function findStockBajo(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, codigo, nombre, categoria, precio_venta, stock_actual, stock_minimo
             FROM producto
             WHERE stock_actual <= stock_minimo
             ORDER BY codigo'
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn (array $row): Producto => $this->mapProducto($row),
            $rows
        );
    }

    /**
     * Descuenta una cantidad del stock del producto indicado.
     *
     * @param int $productoId Identificador del producto afectado.
     * @param int $cantidad Unidades que se descontarán.
     *
     * @return void
     */
    public function descontarStock(int $productoId, int $cantidad): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE producto
             SET stock_actual = stock_actual - :cantidad
             WHERE id = :id'
        );
        $statement->execute([
            'cantidad' => $cantidad,
            'id' => $productoId,
        ]);
    }

    /**
     * Convierte una fila de la base de datos en un modelo Producto.
     *
     * @param array<string, int|string> $row Fila asociativa devuelta por PDO.
     *
     * @return Producto Modelo construido con los datos persistidos.
     */
    private function mapProducto(array $row): Producto
    {
        return new Producto(
            (int) $row['id'],
            (string) $row['codigo'],
            (string) $row['nombre'],
            (string) $row['categoria'],
            (float) $row['precio_venta'],
            (int) $row['stock_actual'],
            (int) $row['stock_minimo']
        );
    }
}
