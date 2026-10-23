
# Prompts usados

## Fase 1 · Generación del D0

Hola, necesito que actúes como analista de sistemas y redactes un D0.md (documento base) en español para este caso:

El Minimarket "La esquina" (San Vicente de Cañete), especializado en venta de abarrotes, lácteos, bebidas, conservas y productos de limpieza. En la actualidad manejan un registro de ventas en cuaderno y revisan stock chequenado estantes; no cuentan con un sistema que sabe cuánto vendió al día y se queda sin productos sin enterarse hasta que se los piden. Necesita una aplicación web interna para:

plain

(1) registrar productos con código, nombre, categoría, precio de venta, stock actual y stock mínimo;  
(2) listar productos resaltando los que están en o por debajo de su stock mínimo;  
(3) registrar una venta de un producto: el cajero elige producto y cantidad; el sistema toma el  
precio vigente, calcula el total, registra fecha y hora y descuenta el stock;  
(4) listar las ventas del día con el total vendido del día.  
Reglas del negocio: Cantidad vendida > 0; no vender más de lo que hay en stock, el precio de venta no puede ser negativo.

Código de producto único; la venta y el descuento de stock ocurren juntos o no ocurre ninguno, es decir, ambos se modifican complementariamente.

Stack: PHP 8 orientado a objetos, MySQL 8 en Laragon, PD0 con sentencias separadas y sin frameworks.

Estructura obligatoria:

Problema descrito en una sola fase.
Usuarios y qué hace cada uno, el cómo trabajan.
Proceso actual y las dificultades que presenta.
Requisitos funcionales, uno por cada punto del caso, sin agregar otros.
Requisitos no funcionales, que incluyan la seguridad que se usará (inyección SQL, XSS, CSRF, credenciales en .env) e integridad de datos (transacción, restricciones en la BD)
Reglas de negocio, las cinco que se mencionan en el caso.
Fuera de alcance: Inicio de sesión, roles, clientes, boletas o facturación electrónica, agentes conversacionales, despliegue al público (subirlo a la nube).
Supuestos:
SUPUESTO: Un solo cajero, una venta = un producto, moneda en soles, entorno Laragon en lugar de XAMPP).
Asegurate de no inventar funcionalidades fuera del caso planteado.

## Fase 2 · Generación del requirements.md y design.md

Ahora, a partir del documento D0.md, genera dos archivos en la raíz del proyecto, no escribas código y no ejecutes aún las tareas del tasks.md.

requirements.md:

Requisitos RF1-RF4 y RNF1... con criterios de aceptación en formato ERAS: "CUANDO <evento> EL SISTEMA DEBERÁ <respuesta>" y "SI <condición> ENTONCES EL SISTEMA DEBERÁ <respuesta>".
Cada RN1-RN5 debe aparecer en al menos un criterio, indica la RN que está trazando.
Al final una tabla de trazabilidad de los RN.
design.md:

Arquitectura en capas Model → Repository → Service → Controller → views: incluye el qué hace cada una y que NO hace cada capa. (Las reglas del negocio solo están en service, el repository solo SQL ya con el PD0 preparado, controller solo recibe la petición, valida CSRF y llama al service, por último el View solo muestran los datos escapados al usuario).
Tablas producto y venta: campos, tipos MySQL, PK, FK, UNIQUE, CHECK y valores por defecto. Usa DECIMAL (10,2) para dinero. La venta guarda producto_id, cantidad, precio_unitario (precio vigente al momento de la venta), total y fecha_hora.
Contratos de clases con firmas PHP tipadas (declare(strict_types=1), namespace App).
Flujo de registrar venta en una sola transacción.
Manejo de errores: registro inexistente devuelve null; error en la base de datos lanza un escepción; el usuario debería ver mensajes claros que indiquen lo que se quiere mostrar.
Conexión: MySQL en 127.0.0.1 puerto 3307; credenciaes solo desde .env (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
Además, una estructura de carpetas bien planteada.

## Fase 2 · Regeneración con agente Spec
Ejecuta nuevamente este código haciendo uso del agente Spec.

## Fase 2 - Corrección de errores generados por formato

Ajusta al formato Spec de Kiro los dos archivos de .kiro/specs/sistema-ventas-stock/,
conservando todo el contenido actual y sus correcciones (bodega_ventas, sin Composer,
puerto 3307, public/index.php). Solo reorganiza y completa:

requirements.md:
- Título: "# Requirements Document".
- "## Introduction": 2-3 líneas con el problema del Minimarket «La Esquina» y el alcance (RF1–RF4).
- "## Glossary": Cajero, Producto, Stock actual, Stock mínimo, Venta, Precio vigente, Transacción.
- "## Requirements": cada RF1–RF4 y RNF como "### Requirement N" con su User Story
  ("Como cajero, quiero..., para...") y "#### Acceptance Criteria" en formato EARS
  («CUANDO ... EL SISTEMA DEBERÁ ...» / «SI ... ENTONCES EL SISTEMA DEBERÁ ...»),
  indicando entre paréntesis la RN que traza. Mantén la tabla de trazabilidad RN → criterios al final.

design.md:
- Secciones: "## Overview", "## Architecture" (capas Model → Repository → Service →
  Controller → views, qué hace y qué no hace cada una, y el flujo de venta con transacción),
  "## Components and Interfaces" (firmas tipadas), "## Data Models" (tablas producto y venta),
  "## Correctness Properties" (una propiedad por cada RN1–RN5 y total = precio × cantidad),
  "## Error Handling" y "## Testing Strategy" (tests/run.php con repositorios falsos en memoria,
  sin PHPUnit ni Composer, los 6 casos mínimos).

No generes código ni ejecutes tareas.

## Fase 3 - Base de datos

Según design.md, genera:
1. database/schema.sql: CREATE DATABASE IF NOT EXISTS bodega_ventas CHARACTER SET utf8mb4
   COLLATE utf8mb4_unicode_ci; USE bodega_ventas; tablas producto y venta con ENGINE=InnoDB,
   PRIMARY KEY, UNIQUE en codigo, FOREIGN KEY venta.producto_id → producto.id con
   ON DELETE RESTRICT, y CHECK para precio_venta >= 0, stock_actual >= 0, stock_minimo >= 0
   y cantidad > 0. Los tipos deben coincidir con Data Models de design.md. Debe ejecutarse
   sin errores en phpMyAdmin con MySQL 8.4.
2. database/seed.sql (empieza con USE bodega_ventas;) con exactamente estos 8 productos:
   P001 Arroz superior 5 kg, Abarrotes, 22.50, 20, 5
   P002 Azúcar rubia 1 kg, Abarrotes, 4.20, 30, 10
   P003 Aceite vegetal 1 L, Abarrotes, 9.90, 12, 4
   P004 Leche evaporada 400 g, Lácteos, 4.00, 48, 12
   P005 Fideo spaghetti 500 g, Abarrotes, 3.50, 25, 8
   P006 Atún en trozos 170 g, Conservas, 6.50, 3, 6
   P007 Gaseosa 1.5 L, Bebidas, 7.00, 18, 6
   P008 Detergente 1 kg, Limpieza, 11.80, 2, 4
3. .env.example en la raíz con DB_HOST=127.0.0.1, DB_PORT=3307, DB_NAME=bodega_ventas,
   DB_USER=root, DB_PASS=
Explica brevemente cada restricción. No generes código PHP.