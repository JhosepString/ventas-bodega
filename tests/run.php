<?php
declare(strict_types=1);

use App\Database\TransaccionInterface;
use App\Exceptions\DatabaseException;
use App\Exceptions\NoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Models\Producto;
use App\Models\Venta;
use App\Repositories\ProductoRepositoryInterface;
use App\Repositories\VentaRepositoryInterface;
use App\Services\VentaService;

require dirname(__DIR__) . '/bootstrap.php';

/**
 * Estado en memoria compartido por los repositorios falsos y la transacción.
 */
final class EntornoFalso
{
    public Producto $producto;

    /** @var array<int, Venta> */
    public array $ventas = [];

    public int $siguienteId = 1;

    public bool $fallarAlCrearVenta = false;

    public bool $fallarAlDescontar = false;

    /** @var array{stock: int, ventas: array<int, Venta>, siguienteId: int}|null */
    public ?array $copiaTransaccional = null;

    /**
     * Inicializa el estado de prueba.
     */
    public function __construct()
    {
        $this->producto = new Producto(1, 'T001', 'Producto de prueba', 'Pruebas', 5.25, 10, 2);
    }
}

/**
 * Repositorio falso de productos para probar el Service sin MySQL.
 */
final class ProductoRepositoryFalso implements ProductoRepositoryInterface
{
    /**
     * Recibe el estado en memoria compartido por la prueba.
     *
     * @param EntornoFalso $entorno Estado compartido.
     */
    public function __construct(private EntornoFalso $entorno)
    {
    }

    /**
     * @param Producto $producto Producto simulado.
     *
     * @return int Identificador de prueba.
     */
    public function create(Producto $producto): int
    {
        return 1;
    }

    /**
     * @param int $id Identificador consultado.
     *
     * @return Producto|null Producto simulado o null si el ID no coincide.
     */
    public function findById(int $id): ?Producto
    {
        return $id === $this->entorno->producto->id ? $this->entorno->producto : null;
    }

    /**
     * @param int $id Identificador consultado.
     *
     * @return Producto|null Producto simulado o null si el ID no coincide.
     */
    public function findByIdForUpdate(int $id): ?Producto
    {
        return $this->findById($id);
    }

    /**
     * @param string $codigo Código consultado.
     *
     * @return Producto|null Producto simulado o null si el código no coincide.
     */
    public function findByCodigo(string $codigo): ?Producto
    {
        return $codigo === $this->entorno->producto->codigo ? $this->entorno->producto : null;
    }

    /**
     * @return array<int, Producto> Lista de prueba.
     */
    public function findAll(): array
    {
        return [$this->entorno->producto];
    }

    /**
     * @return array<int, Producto> Lista de prueba sin evaluación de stock.
     */
    public function findStockBajo(): array
    {
        return [];
    }

    /**
     * @param int $productoId Identificador que se descuenta.
     * @param int $cantidad Unidades que se descuentan.
     *
     * @return void
     */
    public function descontarStock(int $productoId, int $cantidad): void
    {
        if ($this->entorno->fallarAlDescontar) {
            throw new RuntimeException('Fallo simulado al descontar stock.');
        }

        $this->entorno->producto = new Producto(
            $this->entorno->producto->id,
            $this->entorno->producto->codigo,
            $this->entorno->producto->nombre,
            $this->entorno->producto->categoria,
            $this->entorno->producto->precioVenta,
            $this->entorno->producto->stockActual - $cantidad,
            $this->entorno->producto->stockMinimo
        );
    }
}

/**
 * Repositorio falso de ventas para probar el Service sin MySQL.
 */
final class VentaRepositoryFalso implements VentaRepositoryInterface
{
    /**
     * Recibe el estado en memoria compartido por la prueba.
     *
     * @param EntornoFalso $entorno Estado compartido.
     */
    public function __construct(private EntornoFalso $entorno)
    {
    }

    /**
     * @param Venta $venta Venta que se almacenará en memoria.
     *
     * @return int Identificador de venta simulado.
     */
    public function create(Venta $venta): int
    {
        if ($this->entorno->fallarAlCrearVenta) {
            throw new PDOException('Fallo de base de datos simulado.');
        }

        $id = $this->entorno->siguienteId++;
        $this->entorno->ventas[$id] = $venta;

        return $id;
    }

    /**
     * @param string $fecha Fecha consultada.
     *
     * @return array<int, Venta> Ventas simuladas.
     */
    public function findByFecha(string $fecha): array
    {
        return array_values($this->entorno->ventas);
    }

    /**
     * @param string $fecha Fecha consultada.
     *
     * @return float Total simulado.
     */
    public function totalByFecha(string $fecha): float
    {
        return array_sum(array_map(
            static fn (Venta $venta): float => $venta->total,
            $this->entorno->ventas
        ));
    }
}

/**
 * Implementación en memoria del contrato de transacciones.
 */
final class TransaccionFalsa implements TransaccionInterface
{
    public int $inicios = 0;

    public int $confirmaciones = 0;

    public int $reversiones = 0;

    /**
     * Recibe el estado que se debe copiar y restaurar.
     *
     * @param EntornoFalso $entorno Estado compartido.
     */
    public function __construct(private EntornoFalso $entorno)
    {
    }

    /**
     * @return void
     */
    public function begin(): void
    {
        $this->inicios++;
        $this->entorno->copiaTransaccional = [
            'stock' => $this->entorno->producto->stockActual,
            'ventas' => $this->entorno->ventas,
            'siguienteId' => $this->entorno->siguienteId,
        ];
    }

    /**
     * @return void
     */
    public function commit(): void
    {
        $this->confirmaciones++;
        $this->entorno->copiaTransaccional = null;
    }

    /**
     * @return void
     */
    public function rollBack(): void
    {
        $this->reversiones++;
        $snapshot = $this->entorno->copiaTransaccional;

        if ($snapshot === null) {
            return;
        }

        $producto = $this->entorno->producto;
        $this->entorno->producto = new Producto(
            $producto->id,
            $producto->codigo,
            $producto->nombre,
            $producto->categoria,
            $producto->precioVenta,
            $snapshot['stock'],
            $producto->stockMinimo
        );
        $this->entorno->ventas = $snapshot['ventas'];
        $this->entorno->siguienteId = $snapshot['siguienteId'];
        $this->entorno->copiaTransaccional = null;
    }
}

/**
 * Construye un Service y sus dependencias falsas.
 *
 * @param EntornoFalso $entorno Estado compartido de la prueba.
 *
 * @return array{0: VentaService, 1: TransaccionFalsa}
 */
function crearServicio(EntornoFalso $entorno): array
{
    $transaccion = new TransaccionFalsa($entorno);

    return [
        new VentaService(
            new ProductoRepositoryFalso($entorno),
            new VentaRepositoryFalso($entorno),
            $transaccion
        ),
        $transaccion,
    ];
}

/**
 * Comprueba una condición y lanza una excepción si no se cumple.
 *
 * @param bool $condicion Resultado esperado.
 * @param string $mensaje Descripción del fallo.
 *
 * @return void
 */
function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

/**
 * Ejecuta una prueba que debe lanzar una excepción de una clase concreta.
 *
 * @param class-string<Throwable> $tipo Tipo de excepción esperado.
 * @param callable(): void $prueba Operación que se espera que falle.
 *
 * @return void
 */
function esperarExcepcion(string $tipo, callable $prueba): void
{
    try {
        $prueba();
    } catch (Throwable $exception) {
        if ($exception instanceof $tipo) {
            return;
        }

        throw new RuntimeException('Se esperaba ' . $tipo . ' y se recibió ' . $exception::class . '.');
    }

    throw new RuntimeException('No se lanzó la excepción esperada: ' . $tipo . '.');
}

$pruebas = [
    'registra la venta al precio vigente y descuenta stock' => static function (): void {
        $entorno = new EntornoFalso();
        [$service, $transaccion] = crearServicio($entorno);
        $venta = $service->registrar(1, 2);

        comprobar($venta->total === 10.5, 'El total debe usar el precio vigente.');
        comprobar($entorno->producto->stockActual === 8, 'El stock debe disminuir en dos unidades.');
        comprobar($transaccion->confirmaciones === 1, 'La transacción debe confirmarse.');
        comprobar($transaccion->reversiones === 0, 'No debe revertirse una operación correcta.');
    },
    'rechaza cantidad cero antes de iniciar transacción' => static function (): void {
        $entorno = new EntornoFalso();
        [$service, $transaccion] = crearServicio($entorno);

        esperarExcepcion(ValidacionException::class, static fn () => $service->registrar(1, 0));
        comprobar($transaccion->inicios === 0, 'No se debe iniciar transacción para cantidad inválida.');
    },
    'rechaza producto inexistente y revierte' => static function (): void {
        $entorno = new EntornoFalso();
        [$service, $transaccion] = crearServicio($entorno);

        esperarExcepcion(NoEncontradoException::class, static fn () => $service->registrar(999, 1));
        comprobar($transaccion->reversiones === 1, 'La transacción debe revertirse.');
        comprobar($entorno->ventas === [], 'No debe quedar una venta registrada.');
    },
    'rechaza venta superior al stock y revierte' => static function (): void {
        $entorno = new EntornoFalso();
        [$service, $transaccion] = crearServicio($entorno);

        esperarExcepcion(ValidacionException::class, static fn () => $service->registrar(1, 11));
        comprobar($transaccion->reversiones === 1, 'La transacción debe revertirse.');
        comprobar($entorno->producto->stockActual === 10, 'El stock debe conservarse.');
    },
    'revierte si falla el registro de la venta' => static function (): void {
        $entorno = new EntornoFalso();
        $entorno->fallarAlCrearVenta = true;
        [$service, $transaccion] = crearServicio($entorno);

        esperarExcepcion(DatabaseException::class, static fn () => $service->registrar(1, 1));
        comprobar($transaccion->reversiones === 1, 'La transacción debe revertirse.');
        comprobar($entorno->ventas === [], 'No debe persistir una venta parcial.');
        comprobar($entorno->producto->stockActual === 10, 'El stock debe conservarse.');
    },
    'revierte venta si falla el descuento de stock' => static function (): void {
        $entorno = new EntornoFalso();
        $entorno->fallarAlDescontar = true;
        [$service, $transaccion] = crearServicio($entorno);

        esperarExcepcion(DatabaseException::class, static fn () => $service->registrar(1, 1));
        comprobar($transaccion->reversiones === 1, 'La transacción debe revertirse.');
        comprobar($entorno->ventas === [], 'La venta parcial debe eliminarse al revertir.');
        comprobar($entorno->producto->stockActual === 10, 'El stock debe conservarse.');
    },
];

$fallos = 0;

foreach ($pruebas as $nombre => $prueba) {
    try {
        $prueba();
        echo '[OK] ' . $nombre . PHP_EOL;
    } catch (Throwable $exception) {
        $fallos++;
        echo '[FALLO] ' . $nombre . ': ' . $exception->getMessage() . PHP_EOL;
    }
}

echo sprintf('%d pruebas, %d fallos.%s', count($pruebas), $fallos, PHP_EOL);
exit($fallos === 0 ? 0 : 1);
