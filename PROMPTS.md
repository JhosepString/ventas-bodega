
------------------------------------------- PROMPT 1 -------------------------------------------

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

