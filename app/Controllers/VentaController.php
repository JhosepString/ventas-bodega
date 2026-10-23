<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\DatabaseException;
use App\Exceptions\NoEncontradoException;
use App\Exceptions\PeticionInvalidaException;
use App\Exceptions\ValidacionException;
use App\Security\Csrf;
use App\Services\ProductoService;
use App\Services\VentaService;
use Throwable;

/**
 * Atiende las solicitudes web relacionadas con ventas.
 */
final class VentaController
{
    /**
     * Recibe los servicios y utilidades necesarias para ventas.
     *
     * @param VentaService $service Reglas y operaciones de ventas.
     * @param ProductoService $productoService Consulta productos para el formulario.
     * @param Csrf $csrf Validador de peticiones modificadoras.
     */
    public function __construct(
        private readonly VentaService $service,
        private readonly ProductoService $productoService,
        private readonly Csrf $csrf
    ) {
    }

    /**
     * Muestra el formulario para registrar una venta.
     *
     * @return void
     */
    public function mostrarFormularioRegistrar(): void
    {
        $csrfToken = $this->csrf->token();
        $error = null;
        $success = null;
        $formData = [];

        try {
            $productos = $this->productoService->listar();
        } catch (Throwable $exception) {
            error_log('Error al cargar productos para venta: ' . $exception->getMessage());
            $productos = [];
            $error = 'No se pudieron cargar los productos.';
        }

        require dirname(__DIR__, 2) . '/views/ventas/registrar.php';
    }

    /**
     * Verifica CSRF y registra la venta solicitada.
     *
     * @return void
     */
    public function registrar(): void
    {
        $csrfToken = $this->csrf->token();
        $error = null;
        $success = null;
        $formData = [];
        $productos = [];

        try {
            $token = $_POST['_csrf'] ?? null;

            if (!is_string($token) || !$this->csrf->verify($token)) {
                throw new PeticionInvalidaException('La petición no es válida. Vuelva a cargar el formulario.');
            }

            $productoIdValue = $_POST['producto_id'] ?? null;
            $cantidadValue = $_POST['cantidad'] ?? null;

            if (!is_string($productoIdValue) || trim($productoIdValue) === '') {
                throw new ValidacionException('Debe seleccionar un producto.');
            }

            if (!is_string($cantidadValue) || trim($cantidadValue) === '') {
                throw new ValidacionException('Debe indicar la cantidad vendida.');
            }

            $productoId = filter_var($productoIdValue, FILTER_VALIDATE_INT);
            $cantidad = filter_var($cantidadValue, FILTER_VALIDATE_INT);

            if ($productoId === false || $cantidad === false) {
                throw new ValidacionException('El producto y la cantidad deben tener valores válidos.');
            }

            $formData = [
                'producto_id' => (string) $productoId,
                'cantidad' => (string) $cantidad,
            ];

            $venta = $this->service->registrar($productoId, $cantidad);
            $success = sprintf(
                'Venta registrada: %s × %d, total S/ %s.',
                $venta->productoNombre ?? 'producto',
                $venta->cantidad,
                number_format($venta->total, 2, '.', ',')
            );
            $formData = [];
            $productos = $this->cargarProductos();
        } catch (
            PeticionInvalidaException
            | ValidacionException
            | NoEncontradoException $exception
        ) {
            $error = $exception->getMessage();
            $productos = $this->cargarProductos();
        } catch (DatabaseException $exception) {
            error_log('Error de persistencia al registrar venta: ' . $exception->getMessage());
            $error = 'No se pudo registrar la venta. No se aplicaron cambios; inténtelo nuevamente.';
            $productos = $this->cargarProductos();
        } catch (Throwable $exception) {
            error_log('Error inesperado al registrar venta: ' . $exception->getMessage());
            $error = 'Ocurrió un error inesperado. Inténtelo nuevamente.';
            $productos = $this->cargarProductos();
        }

        require dirname(__DIR__, 2) . '/views/ventas/registrar.php';
    }

    /**
     * Muestra las ventas de hoy y el total vendido.
     *
     * @return void
     */
    public function listarDia(): void
    {
        $error = null;
        $ventas = [];
        $total = 0.0;

        try {
            $ventas = $this->service->ventasDelDia();
            $total = $this->service->totalDelDia();
        } catch (Throwable $exception) {
            error_log('Error al listar ventas del día: ' . $exception->getMessage());
            $error = 'No se pudieron cargar las ventas del día.';
        }

        require dirname(__DIR__, 2) . '/views/ventas/listar.php';
    }

    /**
     * Recupera productos para volver a mostrar el formulario tras un error.
     *
     * @return array<int, \App\Models\Producto> Productos disponibles; vacío si la consulta falla.
     */
    private function cargarProductos(): array
    {
        try {
            return $this->productoService->listar();
        } catch (Throwable $exception) {
            error_log('Error al recargar productos del formulario: ' . $exception->getMessage());

            return [];
        }
    }
}
