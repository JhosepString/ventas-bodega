# Requirements Document

## Introduction

El Minimarket «La Esquina» (San Vicente de Cañete) vende abarrotes, lácteos, bebidas, conservas y productos de limpieza. Hoy registra las ventas en un cuaderno y controla el stock revisando los estantes, por lo que no sabe cuánto vendió al día y se queda sin productos sin advertirlo. Este sistema web interno cubre el alcance de los requisitos RF-01 a RF-04: registrar productos, listarlos con resaltado de stock mínimo, registrar ventas y listar las ventas del día con su total.

## Glossary

| Término | Definición |
|---------|------------|
| **Cajero** | Único usuario del sistema; encargado del minimarket que registra productos y ventas. |
| **Producto** | Artículo a la venta, identificado por un código único, con nombre, categoría, precio de venta, stock actual y stock mínimo. |
| **Stock actual** | Cantidad de unidades disponibles de un producto en el momento. |
| **Stock mínimo** | Umbral de unidades a partir del cual el producto se resalta como agotándose o agotado. |
| **Venta** | Operación en la que se vende una cantidad de un producto a su precio vigente; descuenta el stock y registra fecha y hora. |
| **Precio vigente** | Precio de venta del producto al momento de la venta; se copia como `precio_unitario` en el registro de la venta. |
| **Transacción** | Conjunto de operaciones (registro de venta + descuento de stock) que se ejecutan de forma atómica: ambas o ninguna. |

## Requirements

#### Requirement 1

**Registrar productos.** El sistema permitirá registrar un producto con: código (único), nombre, categoría, precio de venta, stock actual y stock mínimo.

**User Story:** Como cajero, quiero registrar productos con código, nombre, categoría, precio de venta, stock actual y stock mínimo, para tener un catálogo centralizado de lo que vendo.

##### Acceptance Criteria

- **CA-01** — **CUANDO** el cajero envía un producto con código, nombre, categoría, precio de venta, stock actual y stock mínimo válidos, **EL SISTEMA DEBERÁ** registrar el producto y mostrar un mensaje de confirmación.
- **CA-02** — **SI** el precio de venta enviado es negativo, **ENTONCES EL SISTEMA DEBERÁ** rechazar el registro e indicar que el precio de venta no puede ser negativo. *(RN-03)*
- **CA-03** — **SI** el código enviado ya existe en otro producto, **ENTONCES EL SISTEMA DEBERÁ** rechazar el registro e indicar que el código de producto debe ser único. *(RN-04)*
- **CA-04** — **SI** falta alguno de los datos obligatorios (código, nombre, categoría, precio, stock actual o stock mínimo), **ENTONCES EL SISTEMA DEBERÁ** rechazar el registro e indicar qué dato falta.
- **CA-05** — **SI** el stock actual o el stock mínimo son negativos, **ENTONCES EL SISTEMA DEBERÁ** rechazar el registro e indicar que el stock no puede ser negativo.

#### Requirement 2

**Listar productos con resaltado de stock mínimo.** El sistema mostrará un listado de todos los productos y resaltará los que están en o por debajo de su stock mínimo.

**User Story:** Como cajero, quiero ver un listado de todos los productos con resaltado de los que están en o por debajo de su stock mínimo, para reponer a tiempo los que se están agotando.

#### Acceptance Criteria

- **CA-06** — **CUANDO** el cajero solicita el listado de productos, **EL SISTEMA DEBERÁ** mostrar todos los productos registrados con su código, nombre, categoría, precio de venta, stock actual y stock mínimo.
- **CA-07** — **SI** un producto tiene stock actual menor o igual a su stock mínimo, **ENTONCES EL SISTEMA DEBERÁ** resaltarlo en el listado para indicar que está en o por debajo del stock mínimo.
- **CA-08** — **SI** un producto tiene stock actual mayor a su stock mínimo, **ENTONCES EL SISTEMA DEBERÁ** mostrarlo sin resaltar.

#### Requirement 3

**Registrar una venta.** El cajero elige un producto y una cantidad; el sistema toma el precio vigente, calcula el total, registra fecha y hora y descuenta el stock. La venta y el descuento de stock ocurren juntos o no ocurre ninguno.

**User Story:** Como cajero, quiero elegir un producto y una cantidad y que el sistema calcule el total, registre fecha y hora y descuente el stock, para vender de forma rápida y sin errores de inventario.

#### Acceptance Criteria

- **CA-09** — **CUANDO** el cajero elige un producto existente y una cantidad válida, **EL SISTEMA DEBERÁ** tomar el precio de venta vigente, calcular el total (precio × cantidad), registrar la fecha y la hora de la venta y descontar la cantidad vendida del stock del producto. *(RN-05)*
- **CA-10** — **SI** la cantidad vendida es menor o igual a cero, **ENTONCES EL SISTEMA DEBERÁ** rechazar la venta e indicar que la cantidad vendida debe ser mayor que cero. *(RN-01)*
- **CA-11** — **SI** la cantidad vendida supera el stock actual del producto, **ENTONCES EL SISTEMA DEBERÁ** rechazar la venta e indicar que no se puede vender más de lo que hay en stock. *(RN-02)*
- **CA-12** — **SI** el producto elegido no existe, **ENTONCES EL SISTEMA DEBERÁ** rechazar la venta e indicar que el producto no existe.
- **CA-13** — **SI** ocurre un error durante el registro de la venta o el descuento de stock, **ENTONCES EL SISTEMA DEBERÁ** revertir ambas operaciones de forma que ninguna quede aplicada e informar del error al usuario. *(RN-05)*

#### Requirement 4

**Listar las ventas del día.** El sistema mostrará las ventas del día (fecha y hora, producto, cantidad, precio y total de cada venta) y el total vendido del día.

**User Story:** Como cajero, quiero ver las ventas del día con su total, para saber cuánto vendí al cerrar el día.

#### Acceptance Criteria

- **CA-14** — **CUANDO** el cajero solicita las ventas del día, **EL SISTEMA DEBERÁ** mostrar las ventas registradas en el día actual con fecha y hora, producto, cantidad, precio unitario y total de cada venta.
- **CA-15** — **CUANDO** el cajero solicita las ventas del día, **EL SISTEMA DEBERÁ** mostrar el total vendido del día, sumando el total de todas las ventas del día.
- **CA-16** — **SI** no existen ventas en el día, **ENTONCES EL SISTEMA DEBERÁ** mostrar un mensaje indicando que no hay ventas registradas y un total vendido del día de cero.

#### Requirement 5

**Seguridad.** El sistema prevenirá inyección SQL, XSS y CSRF, y protegerá las credenciales de la base de datos.

**User Story:** Como cajero, quiero que el sistema sea seguro frente a inyección SQL, XSS y CSRF, para que los datos no sean alterados externamente.

#### Acceptance Criteria

- **CA-17** — **CUANDO** el sistema ejecute cualquier consulta a la base de datos, **EL SISTEMA DEBERÁ** usar PDO con sentencias preparadas y parámetros separados, sin concatenar datos del usuario en la consulta, para prevenir la inyección SQL.
- **CA-18** — **CUANDO** el sistema muestre datos hacia la vista, **EL SISTEMA DEBERÁ** escaparlos (por ejemplo con `htmlspecialchars`) antes de imprimirlos, para prevenir el XSS.
- **CA-19** — **CUANDO** el cajero envíe un formulario que modifique datos, **EL SISTEMA DEBERÁ** validar un token CSRF en el servidor antes de procesar la petición.
- **CA-20** — **SI** un formulario de modificación se envía sin token CSRF o con un token inválido, **ENTONCES EL SISTEMA DEBERÁ** rechazar la petición e informar que la petición no es válida.
- **CA-21** — **CUANDO** el sistema se conecte a la base de datos, **EL SISTEMA DEBERÁ** tomar las credenciales y parámetros desde el archivo `.env` (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) y nunca tenerlos hardcodeados en el código fuente.

#### Requirement 6

**Integridad de datos.** La base de datos garantizará la unicidad del código, la atomicidad de la venta y las restricciones de los valores.

**User Story:** Como cajero, quiero que la base de datos garantice la unicidad del código y la atomicidad de la venta, para que el stock siempre coincida con las ventas registradas.

#### Acceptance Criteria

- **CA-22** — **CUANDO** el sistema registre una venta, **EL SISTEMA DEBERÁ** ejecutar el registro de la venta y el descuento de stock dentro de una transacción de base de datos (BEGIN / COMMIT / ROLLBACK). *(RN-05)*
- **CA-23** — **SI** la transacción de la venta falla en cualquier punto, **ENTONCES EL SISTEMA DEBERÁ** revertirla por completo (ROLLBACK) de forma que la venta y el descuento de stock queden ambos sin aplicar. *(RN-05)*
- **CA-24** — **CUANDO** el sistema registre un producto, **EL SISTEMA DEBERÁ** garantizar que el código sea único mediante una restricción UNIQUE en la base de datos. *(RN-04)*
- **CA-25** — **CUANDO** el sistema registre un producto, **EL SISTEMA DEBERÁ** garantizar que el precio de venta no sea negativo mediante una restricción CHECK en la base de datos. *(RN-03)*
- **CA-26** — **CUANDO** el sistema registre una venta, **EL SISTEMA DEBERÁ** garantizar que la cantidad sea mayor que cero mediante una restricción CHECK en la base de datos. *(RN-01)*

#### Requirement 7

**Tecnología y entorno.** El sistema se construirá en PHP 8 orientado a objetos, sin frameworks, sobre MySQL 8 en Laragon, usando PDO con sentencias preparadas.

**User Story:** Como cajero, quiero que el sistema funcione en el entorno Laragon del minimarket, para usarlo sin instalar nada adicional.

#### Acceptance Criteria

- **CA-27** — **CUANDO** el sistema sea implementado, **EL SISTEMA DEBERÁ** estar construido en PHP 8 orientado a objetos, sin frameworks, sobre MySQL 8 en Laragon, usando PDO con sentencias preparadas.

---

## Reglas de negocio

| Regla | Descripción |
|-------|-------------|
| RN-01 | La cantidad vendida debe ser mayor que cero. |
| RN-02 | No se puede vender más de lo que hay en stock. |
| RN-03 | El precio de venta no puede ser negativo. |
| RN-04 | El código de producto debe ser único. |
| RN-05 | La venta y el descuento de stock ocurren juntos o no ocurre ninguno. |

## Trazabilidad de reglas de negocio

| Regla de negocio | Descripción | Criterios que la trazan |
|------------------|-------------|--------------------------|
| RN-01 | La cantidad vendida debe ser mayor que cero. | CA-10, CA-26 |
| RN-02 | No se puede vender más de lo que hay en stock. | CA-11 |
| RN-03 | El precio de venta no puede ser negativo. | CA-02, CA-25 |
| RN-04 | El código de producto debe ser único. | CA-03, CA-24 |
| RN-05 | La venta y el descuento de stock ocurren juntos o no ocurre ninguno. | CA-09, CA-13, CA-22, CA-23 |

Todas las reglas de negocio (RN-01 a RN-05) están trazadas en al menos un criterio de aceptación.
