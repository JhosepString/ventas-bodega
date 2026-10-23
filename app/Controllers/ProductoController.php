<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\DatabaseException;
use App\Exceptions\PeticionInvalidaException;
use App\Exceptions\ValidacionException;
use App\Models\Producto;
use App\Security\Csrf;
use App\Services\ProductoService;
use PDOException;
use Throwable;

/**
 * Atiende las solicitudes web relacionadas con productos.
 */
final class ProductoController
{
    /**
     * Recibe los servicios y utilidades necesarias para productos.
     *
     * @param ProductoService $service Reglas de negocio de productos.
     * @param Csrf $csrf Validador de peticiones modificadoras.
     */
    public function __construct(
        private readonly ProductoService $service,
        private readonly Csrf $csrf
    ) {
    }

    /**
     * Muestra el formulario de creación de productos.
     *
     * @return void
     */
    public function mostrarFormularioCrear(): void
    {
        $csrfToken = $this->csrf->token();
        $error = null;
        $success = null;
        $formData = [];
        require dirname(__DIR__, 2) . '/views/productos/crear.php';
    }

    /**
     * Valida CSRF, procesa los datos y registra un producto.
     *
     * @return void
     */
    public function crear(): void
    {
        $csrfToken = $this->csrf->token();
        $error = null;
        $success = null;
        $formData = [];

        try {
            $token = $_POST['_csrf'] ?? null;

            if (!is_string($token) || !$this->csrf->verify($token)) {
                throw new PeticionInvalidaException('La petición no es válida. Vuelva a cargar el formulario.');
            }

            foreach (['codigo', 'nombre', 'categoria', 'precio_venta', 'stock_actual', 'stock_minimo'] as $field) {
                $value = $_POST[$field] ?? null;

                if (!is_string($value) || trim($value) === '') {
                    throw new ValidacionException('Falta el dato obligatorio: ' . $field . '.');
                }

                $formData[$field] = trim($value);
            }

            if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/', $formData['precio_venta'])) {
                throw new ValidacionException('El precio de venta debe ser un número válido con hasta dos decimales.');
            }

            $stockActual = filter_var($formData['stock_actual'], FILTER_VALIDATE_INT);
            $stockMinimo = filter_var($formData['stock_minimo'], FILTER_VALIDATE_INT);

            if ($stockActual === false || $stockMinimo === false) {
                throw new ValidacionException('El stock actual y el stock mínimo deben ser números enteros.');
            }

            $producto = new Producto(
                null,
                $formData['codigo'],
                $formData['nombre'],
                $formData['categoria'],
                (float) $formData['precio_venta'],
                $stockActual,
                $stockMinimo
            );

            $this->service->crear($producto);
            $success = 'Producto registrado correctamente.';
            $formData = [];
        } catch (PeticionInvalidaException | ValidacionException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException | DatabaseException $exception) {
            error_log('Error de persistencia al registrar producto: ' . $exception->getMessage());
            $error = 'No se pudo registrar el producto. Inténtelo nuevamente.';
        } catch (Throwable $exception) {
            error_log('Error inesperado al registrar producto: ' . $exception->getMessage());
            $error = 'Ocurrió un error inesperado. Inténtelo nuevamente.';
        }

        require dirname(__DIR__, 2) . '/views/productos/crear.php';
    }

    /**
     * Muestra el listado general de productos.
     *
     * @return void
     */
    public function listar(): void
    {
        $error = null;

        try {
            $productos = $this->service->listar();
        } catch (Throwable $exception) {
            error_log('Error al listar productos: ' . $exception->getMessage());
            $productos = [];
            $error = 'No se pudo cargar el listado de productos.';
        }

        require dirname(__DIR__, 2) . '/views/productos/listar.php';
    }
}
