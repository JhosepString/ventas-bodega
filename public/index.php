<?php
declare(strict_types=1);

use App\Controllers\ProductoController;
use App\Controllers\VentaController;
use App\Database\Connection;
use App\Exceptions\DatabaseException;
use App\Repositories\ProductoRepository;
use App\Repositories\VentaRepository;
use App\Security\Csrf;
use App\Services\ProductoService;
use App\Services\VentaService;

require dirname(__DIR__) . '/bootstrap.php';

try {
    $databaseConfig = require dirname(__DIR__) . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $databaseConfig['host'],
        $databaseConfig['port'],
        $databaseConfig['name'],
        $databaseConfig['charset']
    );
    $pdo = new PDO(
        $dsn,
        $databaseConfig['user'],
        $databaseConfig['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $connection = new Connection($pdo);
    $productoRepository = new ProductoRepository($pdo);
    $ventaRepository = new VentaRepository($pdo);
    $productoService = new ProductoService($productoRepository);
    $ventaService = new VentaService($productoRepository, $ventaRepository, $connection);
    $csrf = new Csrf();
    $productoController = new ProductoController($productoService, $csrf);
    $ventaController = new VentaController($ventaService, $productoService, $csrf);

    $action = $_GET['accion'] ?? 'productos';

    if (!is_string($action)) {
        http_response_code(400);
        echo 'La solicitud no es válida.';
        exit;
    }

    $routes = [
        'productos' => ['GET', [$productoController, 'listar']],
        'crear-producto' => ['GET', [$productoController, 'mostrarFormularioCrear']],
        'guardar-producto' => ['POST', [$productoController, 'crear']],
        'registrar-venta' => ['GET', [$ventaController, 'mostrarFormularioRegistrar']],
        'guardar-venta' => ['POST', [$ventaController, 'registrar']],
        'ventas' => ['GET', [$ventaController, 'listarDia']],
    ];

    if (!isset($routes[$action])) {
        http_response_code(404);
        echo 'La página solicitada no existe.';
        exit;
    }

    [$requiredMethod, $handler] = $routes[$action];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $requiredMethod) {
        header('Allow: ' . $requiredMethod);
        http_response_code(405);
        echo 'Método HTTP no permitido.';
        exit;
    }

    $handler();
} catch (DatabaseException $exception) {
    error_log('Error de base de datos en la aplicación: ' . $exception->getMessage());
    http_response_code(500);
    echo 'No se pudo completar la operación. Inténtelo nuevamente.';
} catch (Throwable $exception) {
    error_log('Error no controlado en la aplicación: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Ocurrió un error inesperado.';
}
