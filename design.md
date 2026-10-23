# design.md — Diseño

**Proyecto:** Sistema de Ventas y Control de Stock para el Minimarket "La Esquina"
**Documento base:** `docs/D0.md`, `requirements.md`
**Stack:** PHP 8 orientado a objetos, MySQL 8 (Laragon), PDO con sentencias preparadas, sin frameworks
**Versión:** 0.1

---

## 1. Arquitectura en capas

El sistema sigue una arquitectura en capas con flujo de datos unidireccional:

```
Controller → Service → Repository → MySQL
                ↑
              Model (transporta datos entre capas)
```

Las vistas se renderizan al final, recibiendo solo datos ya procesados por el Service.

### 1.1 Model

**Qué hace:**
- Representa las entidades del dominio (`Producto`, `Venta`) como objetos con propiedades tipadas.
- Transporta datos entre capas (valores de entrada y de salida).
- Expone getters/setters para acceder a sus atributos de forma controlada.

**Qué NO hace:**
- No accede a la base de datos (no ejecuta SQL).
- No contiene lógica de negocio ni reglas de negocio (no valida cantidad > 0, no calcula totales, no compara stock).
- No conoce el origen de los datos (PDO, HTTP, etc.).

### 1.2 Repository

**Qué hace:**
- Ejecuta SQL contra MySQL usando PDO con sentencias preparadas y parámetros separados.
- Persiste y recupera entidades: `insert`, `select`, `update`.
- Realiza el mapeo del resultado de PDO al objeto Model.
- Expone métodos de lectura/escritura genéricos por entidad (por producto, por venta).

**Qué NO hace:**
- No contiene reglas de negocio (no valida cantidad vs stock, no calcula el total).
- No decide si una operación está permitida.
- No gestiona la transacción de la venta (eso lo coordina el Service; el Repository provee las operaciones atómicas que el Service usa dentro de la transacción).
- No imprime ni formatea datos para el usuario.

### 1.3 Service

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

### 1.4 Controller

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

### 1.5 views

**Qué hace:**
- Muestra los datos al usuario ya procesados.
- Escapa toda salida con `htmlspecialchars` para prevenir XSS.
- Renderiza formularios (incluyendo el campo oculto con el token CSRF) y listados.

**Qué NO hace:**
- No contiene lógica de negocio ni reglas de negocio.
- No accede a la base de datos ni ejecuta SQL.
- No calcula totales ni valida reglas.
- No recibe ni procesa peticiones HTTP directamente (recibe datos ya preparados por el Controller).

---

## 2. Base de datos — Esquema

Motor: **MySQL 8**. Conjunto de caracteres y cotejamiento recomendados: `utf8mb4` / `utf8mb4_unicode_ci`. Los importes usan `DECIMAL(10,2)` (dos decimales).

### 2.1 Tabla `producto`

| Campo | Tipo MySQL | PK | UNIQUE | FK | CHECK | Por defecto | Descripción |
|-------|------------|----|--------|----|--------|-------------|-------------|
| `id` | `INT UNSIGNED` | Sí (AUTO_INCREMENT) | — | — | — | — | Identificador interno. |
| `codigo` | `VARCHAR(50)` | — | Sí | — | — | — | Código único del producto. (RN-04) |
| `nombre` | `VARCHAR(150)` | — | — | — | — | — | Nombre del producto. |
| `categoria` | `VARCHAR(80)` | — | — | — | — | — | Categoría (abarrotes, lácteos, bebidas, conservas, limpieza). |
| `precio_venta` | `DECIMAL(10,2)` | — | — | — | `>= 0` | — | Precio de venta vigente. (RN-03) |
| `stock_actual` | `INT` | — | — | — | `>= 0` | `0` | Unidades disponibles. |
| `stock_minimo` | `INT` | — | — | — | `>= 0` | `0` | Umbral mínimo para resaltar. |

### 2.2 Tabla `venta`

| Campo | Tipo MySQL | PK | UNIQUE | FK | CHECK | Por defecto | Descripción |
|-------|------------|----|--------|----|--------|-------------|-------------|
| `id` | `INT UNSIGNED` | Sí (AUTO_INCREMENT) | — | — | — | — | Identificador de la venta. |
| `producto_id` | `INT UNSIGNED` | — | — | `producto(id)` | — | — | Producto vendido (integridad referencial). |
| `cantidad` | `INT` | — | — | — | `> 0` | — | Unidades vendidas. (RN-01) |
| `precio_unitario` | `DECIMAL(10,2)` | — | — | — | `>= 0` | — | Precio vigente al momento de la venta (copia del `precio_venta`). |
| `total` | `DECIMAL(10,2)` | — | — | — | `>= 0` | — | `precio_unitario × cantidad`. |
| `fecha_hora` | `DATETIME` | — | — | — | — | `CURRENT_TIMESTAMP` | Fecha y hora de la venta. |

### 2.3 DDL (referencia)

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
    CONSTRAINT chk_producto_precio   CHECK (precio_venta >= 0),
    CONSTRAINT chk_producto_stock_act CHECK (stock_actual >= 0),
    CONSTRAINT chk_producto_stock_min CHECK (stock_minimo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE venta (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id    INT UNSIGNED NOT NULL,
    cantidad       INT          NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    total          DECIMAL(10,2) NOT NULL,
    fecha_hora     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_venta_fecha (fecha_hora),
    CONSTRAINT fk_venta_producto FOREIGN KEY (producto_id) REFERENCES producto(id),
    CONSTRAINT chk_venta_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_venta_precio  CHECK (precio_unitario >= 0),
    CONSTRAINT chk_venta_total   CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> El motor `InnoDB` es obligatorio porque soporta transacciones y claves foráneas, necesarias para RN-05 y la integridad referencial.

---

## 3. Contratos de clases

Todos los archivos PHP inician con `declare(strict_types=1);` y usan el namespace `App\`. A continuación solo las **firmas** (contratos), no la implementación.

### 3.1 Configuración y conexión

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
    // Crea la instancia PDO desde variables de entorno (.env).
    public function __construct();

    // Devuelve la conexión PDO con excepciones habilitadas (ERRMODE_EXCEPTION).
    public function pdo(): PDO;

    // Inicia una transacción.
    public function begin(): void;

    // Confirma la transacción.
    public function commit(): void;

    // Revierte la transacción.
    public function rollBack(): void;
}
```

### 3.2 Modelos

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

### 3.3 Repositories

```php
<?php
declare(strict_types=1);

namespace App\Repository;

use App\Model\Producto;
use App\Database\Connection;

interface ProductoRepositoryInterface {
    // Inserta un producto. Lanza excepción si falla la BD (incluye duplicado de código único).
    public function create(Producto $producto): int;

    // Devuelve un Producto por id o null si no existe.
    public function findById(int $id): ?Producto;

    // Devuelve todos los productos. Lanza excepción si falla la BD.
    /** @return Producto[] */
    public function findAll(): array;

    // Descuenta stock de un producto (dentro de una transacción). Lanza excepción si falla.
    public function descontarStock(int $productoId, int $cantidad): void;
}
```

```php
<?php
declare(strict_types=1);

namespace App\Repository;

use App\Model\Venta;
use App\Database\Connection;

interface VentaRepositoryInterface {
    // Inserta una venta (dentro de una transacción). Lanza excepción si falla la BD.
    public function create(Venta $venta): int;

    // Devuelve las ventas de una fecha (día). Lanza excepción si falla la BD.
    /** @return Venta[] */
    public function findByFecha(string $fecha): array;

    // Devuelve el total vendido de una fecha (día). Lanza excepción si falla la BD.
    public function totalByFecha(string $fecha): float;
}
```

### 3.4 Services

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Producto;
use App\Repository\ProductoRepositoryInterface;
use App\Database\Connection;

class ProductoService {
    public function __construct(
        private ProductoRepositoryInterface $repository,
        private Connection $connection
    ) {}

    // Crea un producto validando precio >= 0 (RN-03) y código único (RN-04).
    public function crear(Producto $producto): int;

    // Lista todos los productos.
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

    // Devuelve las ventas del día actual.
    /** @return Venta[] */
    public function ventasDelDia(): array;

    // Devuelve el total vendido del día actual.
    public function totalDelDia(): float;
}
```

### 3.5 Controller

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ProductoService;
use App\Security\Csrf;

class ProductoController {
    public function __construct(private ProductoService $service, private Csrf $csrf) {}

    // Muestra el formulario de registro de producto (GET).
    public function mostrarFormularioCrear(): void;

    // Recibe el POST, valida CSRF, llama al service y muestra resultado (POST).
    public function crear(): void;

    // Muestra el listado de productos con resaltado de stock mínimo (GET).
    public function listar(): void;
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

    // Muestra el formulario de registro de venta (GET).
    public function mostrarFormularioRegistrar(): void;

    // Recibe el POST, valida CSRF, llama al service y muestra resultado (POST).
    public function registrar(): void;

    // Muestra las ventas del día con el total vendido (GET).
    public function listarDia(): void;
}
```

### 3.6 Seguridad

```php
<?php
declare(strict_types=1);

namespace App\Security;

class Csrf {
    // Genera un token CSRF (lo guarda en sesión).
    public function token(): string;

    // Valida el token recibido contra el de sesión.
    public function verify(string $token): bool;
}
```

### 3.7 Excepciones de aplicación

```php
<?php
declare(strict_types=1);

namespace App\Exception;

// Para errores de validación de reglas de negocio (cantidad, stock, precio, código duplicado).
class ValidacionException extends \RuntimeException {}

// Para errores de base de datos que deben revertir la transacción.
class DatabaseException extends \RuntimeException {}

// Para cuando un recurso solicitado no existe (producto inexistente).
class NoEncontradoException extends \RuntimeException {}
```

---

## 4. Flujo de registrar venta en una sola transacción

Este flujo vive en `VentaService::registrar(int $productoId, int $cantidad)` y satisface **RN-05** (la venta y el descuento de stock ocurren juntos o no ocurre ninguno).

1. **Validación de entrada (RN-01):** si `$cantidad <= 0`, lanza `ValidacionException` ("La cantidad vendida debe ser mayor que cero"). No se abre transacción.
2. **Apertura de transacción:** `$connection->begin()`.
3. **Lectura del producto:** `$productoRepository->findById($productoId)`.
   - Si devuelve `null`, lanza `NoEncontradoException` ("El producto no existe"). El bloque finally/e catch ejecuta `rollBack()`.
4. **Validación de stock (RN-02):** si `$cantidad > $producto->stock_actual`, lanza `ValidacionException` ("No se puede vender más de lo que hay en stock"). Se hace `rollBack()`.
5. **Toma del precio vigente:** `$precioUnitario = $producto->precio_venta`.
6. **Cálculo del total:** `$total = $precioUnitario * $cantidad`.
7. **Registro de la venta:** `$ventaRepository->create(new Venta(null, $productoId, $cantidad, $precioUnitario, $total, null))`.
8. **Descuento de stock:** `$productoRepository->descontarStock($productoId, $cantidad)`.
9. **Confirmación:** `$connection->commit()`. La venta y el descuento quedan aplicados juntos.
10. **En caso de error en los pasos 7 u 8:** se captura la excepción, se ejecuta `$connection->rollBack()` y se relanza como `DatabaseException`, de modo que **ninguna** de las dos operaciones queda aplicada (RN-05).
11. **Retorno:** el `Venta` creado (con el `id` y `fecha_hora` asignados por la BD).

Esquema del control de excepciones:

```
begin()
try:
    producto = findById(productoId)
    if producto === null: NoEncontradoException
    if cantidad > producto.stock_actual: ValidacionException
    total = producto.precio_venta * cantidad
    ventaRepository.create(...)
    productoRepository.descontarStock(...)
    commit()
    return Venta
except (PDOException | DatabaseException):
    rollBack()
    throw DatabaseException("Error al registrar la venta")
except (ValidacionException | NoEncontradoException):
    rollBack()
    throw   // se propagan al controller como mensaje claro
```

> Nota: con el supuesto de un solo cajero no hay concurrencia real, por lo que no se requiere `SELECT ... FOR UPDATE`. La transacción es suficiente para garantizar la atomicidad de venta + descuento.

---

## 5. Manejo de errores

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

## 6. Conexión a la base de datos

- Host: `127.0.0.1`
- Puerto: `3307`
- Motor: MySQL 8 (sobre Laragon)
- DSN: `mysql:host=127.0.0.1;port=3307;dbname=<DB_NAME>;charset=utf8mb4`
- Modo de errores: `PDO::ERRMODE_EXCEPTION`
- Las credenciales y parámetros provienen **exclusivamente** del archivo `.env`:

```
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=bodega_ventas
DB_USER=root
DB_PASS=
```

- El archivo `.env` se excluye del control de versiones (`.gitignore`) y se provee un `.env.example` con valores sin secretos.
- Ningún componente hardcodea host, puerto, usuario ni contraseña: todos los leen desde `App\Config\Env`.

---

## 7. Estructura de carpetas

```
ventas-bodega/
├── .env                  # Credenciales y parámetros (NO versionado)
├── .env.example          # Plantilla del .env (versionada, sin secretos)
├── .gitignore
├── index.php             # Front controller / enrutador simple
├── app/
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
│   └── schema.sql         # DDL de producto y venta
├── docs/
│   └── D0.md              # Documento base
├── requirements.md
└── design.md
```

Notas de la estructura:
- `app/` contiene todo el código PHP bajo el namespace `App\` con autoloading PSR-4.
- `views/` queda fuera de `app/` porque contiene plantillas de presentación, no clases.
- `database/schema.sql` centraliza el DDL para versionado y despliegue local.
- El `.env` vive en la raíz y solo es leído por `App\Config\Env`.
- `index.php` es el único punto de entrada público: recibe la petición, enruta al controller correspondiente y renderiza la vista.
