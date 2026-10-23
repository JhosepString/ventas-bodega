# Design Document

## Overview

Sistema web interno para el Minimarket «La Esquina» que permite registrar productos, listarlos con resaltado de stock mínimo, registrar ventas (tomando el precio vigente, calculando el total y descontando el stock) y listar las ventas del día con su total. Construido en PHP 8 orientado a objetos, sin frameworks, sobre MySQL 8 en Laragon, con PDO y sentencias preparadas. La arquitectura separa las responsabilidades en capas para mantener las reglas de negocio aisladas en el Service, el SQL en el Repository y la presentación en las vistas.

**Parámetros de conexión:** host `127.0.0.1`, puerto `3307`, base de datos `bodega_ventas`, credenciales desde `.env`. Sin Composer; el autoloading se resuelve con un cargador simple basado en `spl_autoload_register`. El único punto de entrada público es `public/index.php`.

---

## Architecture

El sistema sigue una arquitectura en capas con flujo de datos unidireccional:

```
Controller → Service → Repository → MySQL
                ↑
            Model (transporta datos entre capas)
```

Las vistas se renderizan al final, recibiendo solo datos ya procesados por el Service.

### Model

**Qué hace:**
- Representa las entidades del dominio (`Producto`, `Venta`) como objetos con propiedades tipadas.
- Transporta datos entre capas (valores de entrada y de salida).
- Expone los atributos de forma controlada mediante constructor readonly.

**Qué NO hace:**
- No accede a la base de datos (no ejecuta SQL).
- No contiene lógica de negocio ni reglas de negocio (no valida cantidad > 0, no calcula totales, no compara stock).
- No conoce el origen de los datos (PDO, HTTP, etc.).

### Repository

**Qué hace:**
- Ejecuta SQL contra MySQL usando PDO con sentencias preparadas y parámetros separados.
- Persiste y recupera entidades: `insert`, `select`, `update`.
- Realiza el mapeo del resultado de PDO al objeto Model.
- Expone métodos de lectura/escritura por entidad (producto, venta).

**Qué NO hace:**
- No contiene reglas de negocio (no valida cantidad vs stock, no calcula el total).
- No decide si una operación está permitida.
- No gestiona la transacción de la venta (eso lo coordina el Service; el Repository provee las operaciones atómicas que el Service usa dentro de la transacción).
- No imprime ni formatea datos para el usuario.

### Service

**Qué hace:**
- Contiene toda la lógica de negocio y las reglas de negocio (RN-01 a RN-05).
- Coordina la transacción de la venta (BEGIN / COMMIT / ROLLBACK).
- Toma el precio vigente, calcula el total y descuenta el stock de forma complementaria.
- Valida que la cantidad > 0 y que no supere el stock actual.
- Llama al Repository para persistir/recuperar datos.
- Devuelve resultados o lanza excepciones; ante un registro inexistente devuelve `null`.

**Qué NO hace:**
- No ejecuta SQL directamente (lo hace a través del Repository).
- No recibe la petición HTTP ni valida CSRF (eso es del Controller).
- No muestra datos al usuario (eso es de la vista).

### Controller

**Qué hace:**
- Recibe la petición HTTP (GET/POST).
- Valida el token CSRF en toda petición POST que modifique datos.
- Extrae y normaliza los datos de entrada (código, nombre, cantidad, etc.) y los pasa al Service.
- Decide qué vista renderizar y con qué datos (entregados por el Service).
- Convierte los errores/excepciones del Service en mensajes claros para el usuario.

**Qué NO hace:**
- No contiene lógica de negocio ni reglas de negocio.
- No ejecuta SQL ni accede a la base de datos.
- No calcula totales ni valida reglas (cantidad, stock, precio).
- No imprime HTML directamente (delega en la vista).

### views

**Qué hace:**
- Muestra los datos al usuario ya procesados.
- Escapa toda salida con `htmlspecialchars` para prevenir XSS.
- Renderiza formularios (incluyendo el campo oculto con el token CSRF) y listados.

**Qué NO hace:**
- No contiene lógica de negocio ni reglas de negocio.
- No accede a la base de datos ni ejecuta SQL.
- No calcula totales ni valida reglas.
- No recibe ni procesa peticiones HTTP directamente (recibe datos ya preparados por el Controller).

### Flujo de registrar venta en una sola transacción

Este flujo vive en `VentaService::registrar(int $productoId, int $cantidad)` y satisface **RN-05** (la venta y el descuento de stock ocurren juntos o no ocurre ninguno).

1. **Validación de entrada (RN-01):** si `$cantidad <= 0`, lanza `ValidacionException` ("La cantidad vendida debe ser mayor que cero"). No se abre transacción.
2. **Apertura de transacción:** `$connection->begin()`.
3. **Lectura del producto:** `$productoRepository->findById($productoId)`. Si devuelve `null`, lanza `NoEncontradoException` ("El producto no existe"). El catch ejecuta `rollBack()`.
4. **Validación de stock (RN-02):** si `$cantidad > $producto->stock_actual`, lanza `ValidacionException` ("No se puede vender más de lo que hay en stock"). Se hace `rollBack()`.
5. **Toma del precio vigente:** `$precioUnitario = $producto->precio_venta`.
6. **Cálculo del total:** `$total = $precioUnitario * $cantidad`.
7. **Registro de la venta:** `$ventaRepository->create(new Venta(null, $productoId, $cantidad, $precioUnitario, $total, null))`.
8. **Descuento de stock:** `$productoRepository->descontarStock($productoId, $cantidad)`.
9. **Confirmación:** `$connection->commit()`. La venta y el descuento quedan aplicados juntos.
10. **En caso de error en los pasos 7 u 8:** se captura la excepción, se ejecuta `$connection->rollBack()` y se relanza como `DatabaseException`, de modo que **ninguna** de las dos operaciones queda aplicada (RN-05).
11. **Retorno:** el `Venta` creado (con el `id` y `fecha_hora` asignados por la BD).

```
begin()
try:
    producto = findById(productoId)
    if producto === null: throw NoEncontradoException
    if cantidad > producto.stock_actual: throw ValidacionException   // RN-02
    total = producto.precio_venta * cantidad
    ventaRepository.create(...)
    productoRepository.descontarStock(...)
    commit()
    return Venta
except (PDOException | DatabaseException):
    rollBack()
    throw DatabaseException("Error al registrar la venta")           // RN-05
except (ValidacionException | NoEncontradoException):
    rollBack()
    throw   // se propagan al controller como mensaje claro
```

> Con el supuesto de un solo cajero no hay concurrencia real, por lo que no se requiere `SELECT ... FOR UPDATE`. La transacción es suficiente para garantizar la atomicidad de venta + descuento.

---

## Components and Interfaces

Todos los archivos PHP inician con `declare(strict_types=1);` y usan el namespace `App\`. A continuación solo las **firmas** (contratos), no la implementación.

### Configuración y conexión

```php
<?php
declare(strict_types=1);

namespace App\Config;

class Env {
    // Lee el archivo .env y expone variables como DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
    public static function load(string $path): void;
    public static function get(string $key, ?string $default = null): ?string;
}
```

```php
<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

class Connection {
    public function __construct();

    // Devuelve la conexión PDO con ERRMODE_EXCEPTION.
    public function pdo(): PDO;

    public function begin(): void;
    public function commit(): void;
    public function rollBack(): void;
}
```

### Autoloader (sin Composer)

```php
<?php
declare(strict_types=1);

// app/autoload.php — cargador simple con spl_autoload_register.
// Mapea App\X\Y → app/X/Y.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        require __DIR__ . '/' . $relative . '.php';
    }
});
```

### Modelos

```php
<?php
declare(strict_types=1);

namespace App\Model;

class Producto {
    public function __construct(
        public readonly ?int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $categoria,
        public readonly float $precio_venta,
        public readonly int $stock_actual,
        public readonly int $stock_minimo
    ) {}
}
```

```php
<?php
declare(strict_types=1);

namespace App\Model;

class Venta {
    public function __construct(
        public readonly ?int $id,
        public readonly int $producto_id,
        public readonly int $cantidad,
        public readonly float $precio_unitario,
        public readonly float $total,
        public readonly ?string $fecha_hora
    ) {}
}
```

### Repositories

```php
<?php
declare(strict_types=1);

namespace App\Repository;

use App\Model\Producto;

interface ProductoRepositoryInterface {
    public function create(Producto $producto): int;
    public function findById(int $id): ?Producto;
    /** @return Producto[] */
    public function findAll(): array;
    public function descontarStock(int $productoId, int $cantidad): void;
}
```

```php
<?php
declare(strict_types=1);

namespace App\Repository;

use App\Model\Venta;

interface VentaRepositoryInterface {
    public function create(Venta $venta): int;
    /** @return Venta[] */
    public function findByFecha(string $fecha): array;
    public function totalByFecha(string $fecha): float;
}
```

### Services

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Producto;
use App\Repository\ProductoRepositoryInterface;

class ProductoService {
    public function __construct(
        private ProductoRepositoryInterface $repository
    ) {}

    // Valida precio >= 0 (RN-03) y código único (RN-04).
    public function crear(Producto $producto): int;

    /** @return Producto[] */
    public function listar(): array;
}
```

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\ProductoRepositoryInterface;
use App\Repository\VentaRepositoryInterface;
use App\Database\Connection;
use App\Model\Venta;

class VentaService {
    public function __construct(
        private ProductoRepositoryInterface $productoRepository,
        private VentaRepositoryInterface $ventaRepository,
        private Connection $connection
    ) {}

    // Registra una venta en una sola transacción (RN-05).
    // Lanza excepción si: cantidad <= 0 (RN-01), cantidad > stock (RN-02), producto no existe.
    public function registrar(int $productoId, int $cantidad): Venta;

    /** @return Venta[] */
    public function ventasDelDia(): array;

    public function totalDelDia(): float;
}
```

### Controllers

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ProductoService;
use App\Security\Csrf;

class ProductoController {
    public function __construct(private ProductoService $service, private Csrf $csrf) {}

    public function mostrarFormularioCrear(): void;  // GET
    public function crear(): void;                     // POST (valida CSRF)
    public function listar(): void;                    // GET
}
```

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\VentaService;
use App\Security\Csrf;

class VentaController {
    public function __construct(private VentaService $service, private Csrf $csrf) {}

    public function mostrarFormularioRegistrar(): void;  // GET
    public function registrar(): void;                   // POST (valida CSRF)
    public function listarDia(): void;                    // GET
}
```

### Seguridad

```php
<?php
declare(strict_types=1);

namespace App\Security;

class Csrf {
    public function token(): string;
    public function verify(string $token): bool;
}
```

### Excepciones de aplicación

```php
<?php
declare(strict_types=1);

namespace App\Exception;

class ValidacionException extends \RuntimeException {}    // cantidad, stock, precio, código duplicado
class DatabaseException extends \RuntimeException {}      // error de BD, revierte transacción
class NoEncontradoException extends \RuntimeException {}  // registro inexistente
```

---

## Data Models

Motor: **MySQL 8**, `utf8mb4` / `utf8mb4_unicode_ci`. Importes en `DECIMAL(10,2)`.

### Tabla `producto`

| Campo | Tipo MySQL | PK | UNIQUE | FK | CHECK | Por defecto | Descripción |
|-------|------------|----|--------|----|--------|-------------|-------------|
| `id` | `INT UNSIGNED` | Sí (AUTO_INCREMENT) | — | — | — | — | Identificador interno. |
| `codigo` | `VARCHAR(50)` | — | Sí | — | — | — | Código único del producto. (RN-04) |
| `nombre` | `VARCHAR(150)` | — | — | — | — | — | Nombre del producto. |
| `categoria` | `VARCHAR(80)` | — | — | — | — | — | Categoría (abarrotes, lácteos, bebidas, conservas, limpieza). |
| `precio_venta` | `DECIMAL(10,2)` | — | — | — | `>= 0` | — | Precio de venta vigente. (RN-03) |
| `stock_actual` | `INT` | — | — | — | `>= 0` | `0` | Unidades disponibles. |
| `stock_minimo` | `INT` | — | — | — | `>= 0` | `0` | Umbral mínimo para resaltar. |

### Tabla `venta`

| Campo | Tipo MySQL | PK | UNIQUE | FK | CHECK | Por defecto | Descripción |
|-------|------------|----|--------|----|--------|-------------|-------------|
| `id` | `INT UNSIGNED` | Sí (AUTO_INCREMENT) | — | — | — | — | Identificador de la venta. |
| `producto_id` | `INT UNSIGNED` | — | — | `producto(id)` | — | — | Producto vendido. |
| `cantidad` | `INT` | — | — | — | `> 0` | — | Unidades vendidas. (RN-01) |
| `precio_unitario` | `DECIMAL(10,2)` | — | — | — | `>= 0` | — | Precio vigente al momento de la venta (copia del `precio_venta`). |
| `total` | `DECIMAL(10,2)` | — | — | — | `>= 0` | — | `precio_unitario × cantidad`. |
| `fecha_hora` | `DATETIME` | — | — | — | — | `CURRENT_TIMESTAMP` | Fecha y hora de la venta. |

### DDL de referencia

```sql
CREATE TABLE producto (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo        VARCHAR(50)  NOT NULL,
    nombre        VARCHAR(150) NOT NULL,
    categoria     VARCHAR(80)  NOT NULL,
    precio_venta  DECIMAL(10,2) NOT NULL,
    stock_actual  INT          NOT NULL DEFAULT 0,
    stock_minimo  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_producto_codigo (codigo),
    CONSTRAINT chk_producto_precio    CHECK (precio_venta >= 0),
    CONSTRAINT chk_producto_stock_act CHECK (stock_actual >= 0),
    CONSTRAINT chk_producto_stock_min CHECK (stock_minimo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE venta (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id     INT UNSIGNED NOT NULL,
    cantidad        INT          NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    total           DECIMAL(10,2) NOT NULL,
    fecha_hora      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_venta_fecha (fecha_hora),
    CONSTRAINT fk_venta_producto FOREIGN KEY (producto_id) REFERENCES producto(id),
    CONSTRAINT chk_venta_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_venta_precio   CHECK (precio_unitario >= 0),
    CONSTRAINT chk_venta_total    CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> El motor `InnoDB` es obligatorio: soporta transacciones y claves foráneas (necesarias para RN-05 y la integridad referencial).

---

## Correctness Properties

| Propiedad | Regla / fórmula | Descripción |
|-----------|-----------------|-------------|
| **CP-01** (RN-01) | `∀ venta v: v.cantidad > 0` | Toda venta registrada tiene una cantidad estrictamente positiva. |
| **CP-02** (RN-02) | `∀ venta v: v.cantidad ≤ stock_actual(producto) al registrar` | La cantidad vendida nunca supera el stock disponible en el momento de la venta. |
| **CP-03** (RN-03) | `∀ producto p: p.precio_venta ≥ 0` | El precio de venta de todo producto es no negativo. |
| **CP-04** (RN-04) | `∀ p1, p2 ∈ productos: p1 ≠ p2 ⟹ p1.codigo ≠ p2.codigo` | No existen dos productos con el mismo código. |
| **CP-05** (RN-05) | `registrar_venta(v) ⟺ descontar_stock(v.cantidad)` | Toda venta registrada implica un descuento equivalente en el stock, y viceversa; si una falla, ninguna se aplica (atomicidad). |
| **CP-06** | `∀ venta v: v.total = v.precio_unitario × v.cantidad` | El total de toda venta es igual al precio unitario vigente multiplicado por la cantidad. |

---

## Error Handling

- **Registro inexistente:** los métodos `findById` y equivalentes devuelven `null`. El Service lo traduce a `NoEncontradoException` con un mensaje claro, que el Controller muestra al usuario.
- **Error de base de datos:** cualquier `PDOException` se envuelve en `DatabaseException` y, si está dentro de una transacción, se ejecuta `rollBack()`. La transacción fallida no deja cambios parciales.
- **Validación de reglas de negocio:** las violaciones de RN-01, RN-02, RN-03 y RN-04 se comunican con `ValidacionException` y un mensaje que indica exactamente qué se quiere mostrar:
  - "La cantidad vendida debe ser mayor que cero."
  - "No se puede vender más de lo que hay en stock."
  - "El precio de venta no puede ser negativo."
  - "El código de producto ya existe (debe ser único)."
- **Vista al usuario:** el Controller captura las excepciones y pasa un mensaje de texto claro a la vista. La vista solo lo escapa (`htmlspecialchars`) y lo muestra; no interpreta excepciones.
- **Modo de errores de PDO:** se configura `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` para que los errores de BD se lancen como excepciones y sean capturables.

---

## Testing Strategy

Los tests se ejecutan con `php tests/run.php` desde la raíz del proyecto. No usan PHPUnit ni Composer. Los repositorios se sustituyen por implementaciones falsas en memoria (arrays PHP) que implementan `ProductoRepositoryInterface` y `VentaRepositoryInterface`, de modo que el `Service` se prueba sin tocar la base de datos. El cargador manual (`app/autoload.php`) se incluye al inicio del test.

### Casos mínimos

| # | Descripción | Lo que verifica | Resultado esperado |
|---|-------------|-----------------|--------------------|
| T-01 | Crear un producto válido (código único, precio ≥ 0, stocks ≥ 0) | RF-01 happy path | El producto se registra; `findAll()` lo incluye con los datos correctos. |
| T-02 | Crear un producto con precio de venta negativo | RN-03 | Lanza `ValidacionException` con el mensaje "El precio de venta no puede ser negativo". El producto no se registra. |
| T-03 | Crear un producto con un código ya existente | RN-04 | Lanza `ValidacionException` con el mensaje "El código de producto ya existe". No se registra un segundo producto. |
| T-04 | Registrar una venta con cantidad ≤ 0 | RN-01 | Lanza `ValidacionException` con el mensaje "La cantidad vendida debe ser mayor que cero". No se descuenta stock. |
| T-05 | Registrar una venta con cantidad mayor al stock actual | RN-02 | Lanza `ValidacionException` con el mensaje "No se puede vender más de lo que hay en stock". No se descuenta stock. |
| T-06 | Registrar una venta válida | RN-05, CP-06 | La venta se registra con `total = precio_unitario × cantidad`; el stock del producto se descuenta en la misma cantidad; si el descuento falla, la venta no se registra. |

### Estructura de `tests/run.php`

El archivo `tests/run.php`:
1. Incluye `app/autoload.php` para cargar las clases de `App\`.
2. Define `ProductoRepositoryFake` y `VentaRepositoryFake` (implementan las interfaces con arrays en memoria).
3. Construye `VentaService` (y `ProductoService`) con los repositorios falsos.
4. Ejecuta los 6 casos anteriores, cada uno con `assert()` o comparaciones simples; imprime "OK" o el error por consola.
5. Devuelve código de salida `0` si todos pasan, distinto de `0` si alguno falla.

---

## Estructura de carpetas

```
ventas-bodega/
├── .env                    # Credenciales y parámetros (NO versionado)
├── .env.example            # Plantilla del .env (versionada, sin secretos)
├── .gitignore
├── public/
│   └── index.php           # Front controller / enrutador (única entrada pública)
├── app/
│   ├── autoload.php        # Cargador simple (spl_autoload_register, sin Composer)
│   ├── Config/
│   │   └── Env.php
│   ├── Database/
│   │   └── Connection.php
│   ├── Model/
│   │   ├── Producto.php
│   │   └── Venta.php
│   ├── Repository/
│   │   ├── ProductoRepositoryInterface.php
│   │   ├── ProductoRepository.php
│   │   ├── VentaRepositoryInterface.php
│   │   └── VentaRepository.php
│   ├── Service/
│   │   ├── ProductoService.php
│   │   └── VentaService.php
│   ├── Controller/
│   │   ├── ProductoController.php
│   │   └── VentaController.php
│   ├── Security/
│   │   └── Csrf.php
│   └── Exception/
│       ├── ValidacionException.php
│       ├── DatabaseException.php
│       └── NoEncontradoException.php
├── views/
│   ├── layouts/
│   │   └── header.php
│   ├── productos/
│   │   ├── crear.php
│   │   └── listar.php
│   └── ventas/
│       ├── registrar.php
│       └── listar.php
├── database/
│   └── schema.sql           # DDL de producto y venta
├── tests/
│   └── run.php             # Tests sin PHPUnit ni Composer
└── docs/
    └── D0.md               # Documento base
```

Notas de la estructura:
- `public/` es la raíz web; el servidor de Laragon apunta a este directorio. El resto del código queda fuera de la raíz pública.
- `app/` contiene todo el código PHP bajo el namespace `App\` con el cargador manual `app/autoload.php` (sin Composer).
- `views/` queda fuera de `app/` porque contiene plantillas de presentación, no clases.
- `database/schema.sql` centraliza el DDL para versionado.
- El `.env` vive en la raíz del proyecto y solo es leído por `App\Config\Env`.
- `tests/run.php` se ejecuta con `php tests/run.php` sin dependencias externas.

### .env (referencia)

```
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=bodega_ventas
DB_USER=root
DB_PASS=
```

- DSN: `mysql:host=127.0.0.1;port=3307;dbname=bodega_ventas;charset=utf8mb4`
- Modo de errores: `PDO::ERRMODE_EXCEPTION`
- El archivo `.env` se excluye del control de versiones (`.gitignore`) y se provee `.env.example` con valores sin secretos.
- Ningún componente hardcodea host, puerto, usuario ni contraseña: todos los leen desde `App\Config\Env`.
